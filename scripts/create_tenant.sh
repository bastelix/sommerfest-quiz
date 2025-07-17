#!/bin/sh
# Create a new tenant and reload nginx proxy
set -e

if [ "$#" -ne 1 ]; then
  echo "Usage: $0 <subdomain>" >&2
  exit 1
fi

SUBDOMAIN="$1"
BASE_DIR="$(dirname "$0")/.."
ENV_FILE="$BASE_DIR/.env"
DOMAIN="$(grep '^DOMAIN=' "$ENV_FILE" | cut -d '=' -f2)"
CLIENT_MAX_BODY_SIZE="$(grep '^CLIENT_MAX_BODY_SIZE=' "$ENV_FILE" | cut -d '=' -f2)"

[ -z "$CLIENT_MAX_BODY_SIZE" ] && CLIENT_MAX_BODY_SIZE="50m"

if [ -z "$DOMAIN" ]; then
  echo "DOMAIN not found in $ENV_FILE" >&2
  exit 1
fi

curl -s -X POST \
  -H 'Content-Type: application/json' \
  -d "{\"subdomain\":\"$SUBDOMAIN\"}" \
  "http://$DOMAIN/tenant"

mkdir -p "$BASE_DIR/vhost.d"
echo "client_max_body_size $CLIENT_MAX_BODY_SIZE;" > "$BASE_DIR/vhost.d/${SUBDOMAIN}.$DOMAIN"

docker compose exec nginx nginx -s reload
