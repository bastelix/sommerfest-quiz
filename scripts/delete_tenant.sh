#!/bin/sh
# Delete a tenant and reload nginx proxy
set -e

if [ "$#" -ne 1 ]; then
  echo "Usage: $0 <subdomain>" >&2
  exit 1
fi

SUBDOMAIN="$1"
BASE_DIR="$(dirname "$0")/.."
ENV_FILE="$BASE_DIR/sample.env"
DOMAIN="$(grep '^DOMAIN=' "$ENV_FILE" | cut -d '=' -f2)"

if [ -z "$DOMAIN" ]; then
  echo "DOMAIN not found in $ENV_FILE" >&2
  exit 1
fi

curl -s -X DELETE \
  -H 'Content-Type: application/json' \
  -d "{\"subdomain\":\"$SUBDOMAIN\"}" \
  "http://$DOMAIN/tenant"

rm -f "$BASE_DIR/vhost.d/${SUBDOMAIN}.$DOMAIN"

docker compose exec nginx-proxy nginx -s reload
