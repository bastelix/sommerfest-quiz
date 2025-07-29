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
NGINX_RELOAD="$(grep '^NGINX_RELOAD=' "$ENV_FILE" | cut -d '=' -f2)"
RELOADER_URL="$(grep '^NGINX_RELOADER_URL=' "$ENV_FILE" | cut -d '=' -f2)"
RELOAD_TOKEN="$(grep '^NGINX_RELOAD_TOKEN=' "$ENV_FILE" | cut -d '=' -f2)"
NGINX_CONTAINER="$(grep '^NGINX_CONTAINER=' "$ENV_FILE" | cut -d '=' -f2)"

[ -z "$CLIENT_MAX_BODY_SIZE" ] && CLIENT_MAX_BODY_SIZE="50m"
[ -z "$NGINX_RELOAD" ] && NGINX_RELOAD=1
[ -z "$NGINX_CONTAINER" ] && NGINX_CONTAINER="nginx"

if [ -z "$DOMAIN" ]; then
  echo "DOMAIN not found in $ENV_FILE" >&2
  exit 1
fi

curl -s -X POST \
  -H 'Content-Type: application/json' \
  -d "{\"subdomain\":\"$SUBDOMAIN\"}" \
  "http://$DOMAIN/tenants"

mkdir -p "$BASE_DIR/vhost.d"
echo "client_max_body_size $CLIENT_MAX_BODY_SIZE;" > "$BASE_DIR/vhost.d/${SUBDOMAIN}.$DOMAIN"
if [ -n "$RELOADER_URL" ]; then
  curl -s -X POST -H "X-Token: $RELOAD_TOKEN" "$RELOADER_URL"
elif [ "$NGINX_RELOAD" = "1" ]; then
  docker compose exec "$NGINX_CONTAINER" nginx -s reload
fi
