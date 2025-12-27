#!/bin/sh
# Collect domains and trigger a full certificate request cycle
set -e

if [ -z "$1" ]; then
  echo "Usage: $0 <comma-separated-domains>" >&2
  exit 1
fi

raw_input=$(printf '%s' "$1" | tr '\n' ',')
normalized=$(printf '%s' "$raw_input" | sed 's/,,*/,/g; s/^,//; s/,$//')
if [ -z "$normalized" ]; then
  echo "No domains supplied" >&2
  exit 1
fi

domains=""
wildcards=""

for candidate in $(printf '%s' "$normalized" | tr ',' ' '); do
  cleaned=$(printf '%s' "$candidate" | tr '[:upper:]' '[:lower:]' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
  if [ -z "$cleaned" ]; then
    continue
  fi

  case "$cleaned" in
    \*.*)
      wildcards="$wildcards $cleaned"
      continue
      ;;
  esac

  case " $domains " in
    *" $cleaned "*)
      ;;
    *)
      domains="$domains $cleaned"
      ;;
  esac
done

if [ -n "$wildcards" ]; then
  echo "Wildcard domains cannot be processed via HTTP-01. Remove them or provision a wildcard certificate manually." >&2
  echo "Rejected wildcard entries:${wildcards}" >&2
  exit 1
fi

domain_list=$(printf '%s' "$domains" | sed 's/^ //; s/  */ /g; s/ /,/g')
domain_list=$(printf '%s' "$domain_list" | sed 's/^,//; s/,$//; s/,,*/,/g')
if [ -z "$domain_list" ]; then
  echo "No valid domains supplied after filtering" >&2
  exit 1
fi

export MARKETING_DOMAINS="$domain_list"
echo "[request_ssl] Domains: $MARKETING_DOMAINS"

SCRIPT_DIR="$(dirname "$0")"
LOG_FILE="$SCRIPT_DIR/../logs/ssl_provisioning.log"
mkdir -p "$(dirname "$LOG_FILE")"

LOCK_FILE="$SCRIPT_DIR/../logs/ssl_provisioning.lock"

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

primary_domain=$(printf '%s' "$domain_list" | cut -d ',' -f1)
cert_path="$SCRIPT_DIR/../certs/${primary_domain}.crt"
freshness_window_seconds=$((6 * 3600))
minimum_validity_days=7

if [ -f "$cert_path" ]; then
  now_ts=$(date +%s)
  cert_mtime=$(stat -c %Y "$cert_path" 2>/dev/null || stat -f %m "$cert_path" 2>/dev/null || echo "")
  expiry_raw=$(openssl x509 -enddate -noout -in "$cert_path" 2>/dev/null | cut -d '=' -f2-)
  expiry_ts=""

  if [ -n "$expiry_raw" ]; then
    expiry_ts=$(date -d "$expiry_raw" +%s 2>/dev/null || date -j -f "%b %d %T %Y %Z" "$expiry_raw" +%s 2>/dev/null || echo "")
  fi

  if [ -n "$cert_mtime" ]; then
    age_seconds=$((now_ts - cert_mtime))
  else
    age_seconds=""
  fi

  if [ -n "$expiry_ts" ]; then
    seconds_left=$((expiry_ts - now_ts))
  else
    seconds_left=""
  fi

  if [ -n "$age_seconds" ] && [ "$age_seconds" -lt "$freshness_window_seconds" ] && \
     [ -n "$seconds_left" ] && [ "$seconds_left" -gt $((minimum_validity_days * 86400)) ]; then
    echo "[$(date -Iseconds)] certificate for $primary_domain is fresh; skipping renewal trigger" >> "$LOG_FILE"
    exit 0
  fi
fi

echo "[$(date -Iseconds)] trigger renew_ssl for domains: $MARKETING_DOMAINS" >> "$LOG_FILE"

max_attempts=3
backoff_seconds=5
attempt=1

while [ "$attempt" -le "$max_attempts" ]; do
  if sh "$SCRIPT_DIR/renew_ssl.sh" --recreate --main; then
    exit 0
  fi

  attempt=$((attempt + 1))
  if [ "$attempt" -le "$max_attempts" ]; then
    sleep $((backoff_seconds * (attempt - 1)))
  fi
done

echo "[$(date -Iseconds)] renew_ssl.sh failed after $max_attempts attempts" >> "$LOG_FILE"
exit 1
