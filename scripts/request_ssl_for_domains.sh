#!/bin/sh
# Collect domains and trigger a certbot-marketing recreate so docker-gen/acme-companion picks up changes
set -e

if [ -z "$1" ]; then
  echo "Usage: $0 <comma-separated-domains>" >&2
  exit 1
fi

if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  DOCKER_COMPOSE="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
  DOCKER_COMPOSE="docker-compose"
else
  echo "docker compose not available" >&2
  exit 1
fi

SCRIPT_DIR="$(dirname "$0")"
LOG_FILE="$SCRIPT_DIR/../logs/ssl_provisioning.log"
mkdir -p "$(dirname "$LOG_FILE")"

LOCK_FILE="$SCRIPT_DIR/../logs/ssl_provisioning.lock"
LOCK_ACQUIRED=""

cleanup_lock() {
  if [ -n "$LOCK_ACQUIRED" ] && [ "$LOCK_ACQUIRED" -eq 1 ]; then
    rm -f "$LOCK_FILE"
  fi
}

trap cleanup_lock EXIT

if ! ( set -o noclobber; echo "$$" > "$LOCK_FILE" ) 2>/dev/null; then
  echo "[$(date -Iseconds)] renewal already running; skipping new trigger" >> "$LOG_FILE"
  exit 0
fi

LOCK_ACQUIRED=1

normalize_domains() {
  printf '%s' "$1" | tr ',\n' '\n\n' |
    tr '[:upper:]' '[:lower:]' |
    sed -E 's#https?://##g' |
    sed -E 's#/.*##' |
    sed -E 's/\?.*$//' |
    sed -E 's/#.*$//' |
    sed -E 's/:.*$//' |
    sed 's/^[[:space:]]*//;s/[[:space:]]*$//' |
    sed '/^$/d' |
    grep -v '\*' |
    sort -u
}

raw_input=$(printf '%s' "$1")
normalized_list=$(normalize_domains "$raw_input")

if [ -z "$normalized_list" ]; then
  echo "No valid domains supplied after normalization" >&2
  echo "[$(date -Iseconds)] rejected request: empty domain list from input '$raw_input'" >> "$LOG_FILE"
  exit 1
fi

domain_csv=$(printf '%s' "$normalized_list" | paste -sd, -)

echo "[request_ssl] Domains: $domain_csv"

EMAIL_FALLBACK=${MARKETING_SSL_CONTACT_EMAIL:-admin@calhelp.de}
LE_EMAIL=${LETSENCRYPT_EMAIL:-$EMAIL_FALLBACK}

if ! MARKETING_LETSENCRYPT_HOST="$domain_csv" LETSENCRYPT_EMAIL="$LE_EMAIL" $DOCKER_COMPOSE up -d --force-recreate certbot-marketing >/dev/null 2>&1; then
  echo "[$(date -Iseconds)] failed to recreate certbot-marketing for domains: $domain_csv" >> "$LOG_FILE"
  exit 1
fi

echo "[$(date -Iseconds)] recreated certbot-marketing for domains: $domain_csv" >> "$LOG_FILE"
