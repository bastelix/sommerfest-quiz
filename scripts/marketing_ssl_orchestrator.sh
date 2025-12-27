#!/usr/bin/env bash
# Orchestrate marketing SSL provisioning via REST API and Docker container recreation
set -euo pipefail

# Configuration
API_URL=${MARKETING_SSL_API_URL:-"http://localhost:8080/api/admin/marketing-domains"}
API_TOKEN=${MARKETING_SSL_API_TOKEN:-""}
PROJECT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
DEFAULT_EMAIL=${MARKETING_SSL_CONTACT_EMAIL:-"admin@calhelp.de"}

usage() {
  cat >&2 <<'USAGE'
Usage: marketing_ssl_orchestrator.sh --namespace <name> [--dry-run]

Environment variables:
  MARKETING_SSL_API_URL     Base URL for the marketing domains endpoint (default: http://localhost:8080/api/admin/marketing-domains)
  MARKETING_SSL_API_TOKEN   Bearer token for the API request (required)
  MARKETING_SSL_CONTACT_EMAIL Contact email for LetsEncrypt (default: admin@calhelp.de)
USAGE
}

namespace=""
dry_run=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --namespace)
      namespace="$2"
      shift 2
      ;;
    --dry-run)
      dry_run=1
      shift 1
      ;;
    --help|-h)
      usage
      exit 0
      ;;
    *)
      usage
      exit 1
      ;;
  esac
done

if [[ -z "$namespace" ]]; then
  usage
  exit 1
fi

if [[ -z "$API_TOKEN" ]]; then
  printf 'API token is required (set MARKETING_SSL_API_TOKEN)\n' >&2
  exit 1
fi

LOG_DIR="/var/log/marketing-ssl"
mkdir -p "$LOG_DIR"
LOG_FILE="$LOG_DIR/${namespace}.log"
ENV_FILE="$PROJECT_DIR/.env.marketing.${namespace}"
LOCK_FILE="/var/lock/marketing-ssl.${namespace}.lock"
SERVICE="certbot-marketing-${namespace}"

log_entry() {
  local action="$1"
  local old_domains="$2"
  local new_domains="$3"
  printf '%s namespace=%s action=%s old="%s" new="%s"\n' "$(date -Iseconds)" "$namespace" "$action" "$old_domains" "$new_domains" >> "$LOG_FILE"
}

# Lock per namespace without waiting
exec {lock_fd}>"$LOCK_FILE" || exit 0
if ! flock -n "$lock_fd"; then
  exit 0
fi

# Fetch domains via REST API
separator='?'
if [[ "$API_URL" == *?* ]]; then
  separator='&'
fi

response=$(curl -fsS \
  -H "Authorization: Bearer ${API_TOKEN}" \
  -H "Accept: application/json" \
  "${API_URL}${separator}namespace=${namespace}")

mapfile -t domains < <(printf '%s' "$response" | jq -r '.domains[]')

normalize_domains() {
  printf '%s\n' "$@" | \
    tr '[:upper:]' '[:lower:]' | \
    sed -E 's~https?://~~g' | \
    sed -E 's~/.*~~' | \
    sed '/^$/d' | \
    grep -v '\*' | \
    sort -u
}

normalized_list=$(normalize_domains "${domains[@]:-}")

new_csv=""
if [[ -n "$normalized_list" ]]; then
  new_csv=$(printf '%s' "$normalized_list" | paste -sd, -)
fi

old_csv=""
if [[ -f "$ENV_FILE" ]]; then
  old_csv=$(sed -n 's/^MARKETING_LETSENCRYPT_HOST=//p' "$ENV_FILE" | head -n 1)
fi

if [[ "$dry_run" -eq 1 ]]; then
  log_entry "DRY_RUN" "$old_csv" "$new_csv"
  exit 0
fi

if [[ -z "$new_csv" ]]; then
  log_entry "NO_DOMAINS" "$old_csv" ""
  exit 0
fi

if [[ "$new_csv" == "$old_csv" ]]; then
  log_entry "NO_CHANGE" "$old_csv" "$new_csv"
  exit 0
fi

log_entry "CHANGE" "$old_csv" "$new_csv"

printf 'MARKETING_LETSENCRYPT_HOST=%s\nLETSENCRYPT_EMAIL=%s\n' "$new_csv" "$DEFAULT_EMAIL" > "$ENV_FILE"

(
  cd "$PROJECT_DIR"
  docker compose --env-file "$ENV_FILE" up -d --force-recreate "$SERVICE" >/dev/null
)

log_entry "RECREATED" "$old_csv" "$new_csv"
log_entry "DONE" "$old_csv" "$new_csv"

