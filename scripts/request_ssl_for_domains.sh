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

echo "[$(date -Iseconds)] trigger renew_ssl for domains: $MARKETING_DOMAINS" >> "$LOG_FILE"

sh "$SCRIPT_DIR/renew_ssl.sh" --recreate --main
