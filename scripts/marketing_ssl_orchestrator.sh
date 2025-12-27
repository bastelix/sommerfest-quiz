#!/usr/bin/env bash
# Orchestrate marketing SSL provisioning via REST API and Docker container recreation
set -euo pipefail

API_URL=${MARKETING_SSL_API_URL:-"http://localhost:8080/api/admin/marketing-domains"}
API_TOKEN=${MARKETING_SSL_API_TOKEN:-""}
PROJECT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)
DEFAULT_EMAIL=${MARKETING_SSL_CONTACT_EMAIL:-${LETSENCRYPT_EMAIL:-"admin@calhelp.de"}}

usage() {
  cat >&2 <<'USAGE'
Usage: marketing_ssl_orchestrator.sh [--namespace <name> ...] [--host <domain> ...] [--dry-run]

Options:
  --namespace <name>   Restrict provisioning to one or more namespaces. When omitted,
                       all marketing domains are collected from the API endpoint.
  --host <domain>      Provision the given domain(s) directly without querying the API.
  --dry-run            Compute the domain list but skip Docker recreation.

Environment variables:
  MARKETING_SSL_API_URL      Base URL for the marketing domains endpoint (default: http://localhost:8080/api/admin/marketing-domains)
  MARKETING_SSL_API_TOKEN    Bearer token for the API request (required unless using --host)
  MARKETING_SSL_CONTACT_EMAIL Contact email for LetsEncrypt (default: admin@calhelp.de)
USAGE
}

namespaces=()
hosts=()
dry_run=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --namespace)
      namespaces+=("$2")
      shift 2
      ;;
    --host)
      hosts+=("$2")
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

LOG_DIR="/var/log/marketing-ssl"
mkdir -p "$LOG_DIR"
LOG_FILE="$LOG_DIR/marketing-ssl.log"
LOCK_FILE="/var/lock/marketing-ssl.lock"
SERVICE="certbot-marketing"

log_entry() {
  local action="$1"
  local details="$2"
  printf '%s action=%s %s\n' "$(date -Iseconds)" "$action" "$details" >> "$LOG_FILE"
}

# Lock to prevent concurrent runs
exec {lock_fd}>"$LOCK_FILE" || exit 0
if ! flock -n "$lock_fd"; then
  exit 0
fi

if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  DOCKER_COMPOSE=(docker compose)
elif command -v docker-compose >/dev/null 2>&1; then
  DOCKER_COMPOSE=(docker-compose)
else
  printf 'docker compose not available\n' >&2
  exit 1
fi

normalize_domains() {
  printf '%s\n' "$@" |
    tr '[:upper:]' '[:lower:]' |
    sed -E 's#https?://##g' |
    sed -E 's#/.*##' |
    sed -E 's/\?.*$//' |
    sed -E 's/#.*$//' |
    sed -E 's/:.*$//' |
    sed '/^$/d' |
    grep -v '\*' |
    sort -u
}

to_csv() {
  if [[ -z "$1" ]]; then
    return 0
  fi

  paste -sd, -
}

fetch_domains_for_namespace() {
  local ns="$1"
  local separator='?'
  if [[ "$API_URL" == *?* ]]; then
    separator='&'
  fi

  local url="$API_URL"
  if [[ -n "$ns" ]]; then
    url="${API_URL}${separator}namespace=${ns}"
  fi

  curl -fsS \
    -H "Authorization: Bearer ${API_TOKEN}" \
    -H "Accept: application/json" \
    "$url" | jq -r '.domains[]'
}

collect_domains() {
  local aggregated=()

  if [[ ${#hosts[@]} -gt 0 ]]; then
    aggregated=("${hosts[@]}")
  elif [[ ${#namespaces[@]} -eq 0 ]]; then
    while IFS= read -r domain; do
      aggregated+=("$domain")
    done < <(fetch_domains_for_namespace "")
  else
    for ns in "${namespaces[@]}"; do
      while IFS= read -r domain; do
        aggregated+=("$domain")
      done < <(fetch_domains_for_namespace "$ns")
    done
  fi

  normalize_domains "${aggregated[@]}"
}

if [[ ${#hosts[@]} -eq 0 && -z "$API_TOKEN" ]]; then
  printf 'API token is required (set MARKETING_SSL_API_TOKEN)\n' >&2
  exit 1
fi

current_container_domains() {
  docker inspect -f '{{range .Config.Env}}{{println .}}{{end}}' "$SERVICE" 2>/dev/null | \
    sed -n 's/^LETSENCRYPT_HOST=//p' | head -n1 | \
    tr ',' '\n' | normalize_domains
}

normalized_list=$(collect_domains)

if [[ -z "$normalized_list" ]]; then
  log_entry "NO_DOMAINS" "reason=empty-response"
  exit 0
fi

new_csv=$(printf '%s\n' "$normalized_list" | to_csv)

if [[ -z "$new_csv" ]]; then
  log_entry "NO_DOMAINS" "reason=empty-csv"
  exit 0
fi

current_csv=$(current_container_domains | to_csv)

if [[ "$dry_run" -eq 1 ]]; then
  log_entry "DRY_RUN" "current='${current_csv:-}' new='$new_csv'"
  exit 0
fi

if [[ -n "$current_csv" && "$current_csv" == "$new_csv" ]]; then
  log_entry "NO_CHANGE" "domains='$new_csv'"
  exit 0
fi

log_entry "CHANGE" "current='${current_csv:-}' new='$new_csv'"

(
  cd "$PROJECT_DIR"
  MARKETING_LETSENCRYPT_HOST="$new_csv" LETSENCRYPT_EMAIL="$DEFAULT_EMAIL" "${DOCKER_COMPOSE[@]}" up -d --force-recreate "$SERVICE" >/dev/null
)

log_entry "RECREATED" "domains='$new_csv'"
log_entry "DONE" "domains='$new_csv'"
