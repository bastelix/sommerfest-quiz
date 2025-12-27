#!/bin/sh
# Collect domains and trigger a full certificate request cycle
set -e

if [ -z "$1" ]; then
  echo "Usage: $0 <comma-separated-domains>" >&2
  exit 1
fi

domain_list=$(printf '%s' "$1" | tr '\n' ',' | sed 's/,,*/,/g; s/^,//; s/,$//')
if [ -z "$domain_list" ]; then
  echo "No domains supplied" >&2
  exit 1
fi

export MARKETING_DOMAINS="$domain_list"
echo "[request_ssl] Domains: $MARKETING_DOMAINS"

SCRIPT_DIR="$(dirname "$0")"
LOG_FILE="$SCRIPT_DIR/../logs/ssl_provisioning.log"
mkdir -p "$(dirname "$LOG_FILE")"

echo "[$(date -Iseconds)] trigger renew_ssl for domains: $MARKETING_DOMAINS" >> "$LOG_FILE"

sh "$SCRIPT_DIR/renew_ssl.sh" --recreate --main
