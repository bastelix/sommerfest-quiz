#!/bin/sh
# Delete a tenant and reload nginx proxy
set -e

if [ "$#" -ne 1 ]; then
  echo "Usage: $0 <subdomain>" >&2
  exit 1
fi

SUBDOMAIN="$1"
BASE_DIR="$(dirname "$0")/.."
ENV_FILE="$BASE_DIR/.env"
DOMAIN="$(grep '^DOMAIN=' "$ENV_FILE" | cut -d '=' -f2)"
NGINX_RELOAD="$(grep '^NGINX_RELOAD=' "$ENV_FILE" | cut -d '=' -f2)"
RELOADER_URL="$(grep '^NGINX_RELOADER_URL=' "$ENV_FILE" | cut -d '=' -f2)"
RELOAD_TOKEN="$(grep '^NGINX_RELOAD_TOKEN=' "$ENV_FILE" | cut -d '=' -f2)"
NGINX_CONTAINER="$(grep '^NGINX_CONTAINER=' "$ENV_FILE" | cut -d '=' -f2)"

[ -z "$NGINX_RELOAD" ] && NGINX_RELOAD=1
[ -z "$NGINX_CONTAINER" ] && NGINX_CONTAINER="nginx"

# detect docker compose only when a reload via Docker is required
if [ "$NGINX_RELOAD" = "1" ] && [ -z "$RELOADER_URL" ]; then
  if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
    DOCKER_COMPOSE="docker compose"
  elif command -v docker-compose >/dev/null 2>&1; then
    DOCKER_COMPOSE="docker-compose"
  else
    echo "docker compose oder docker-compose ist nicht verf\u00fcgbar" >&2
    exit 1
  fi
fi

if [ -z "$DOMAIN" ]; then
  echo "DOMAIN not found in $ENV_FILE" >&2
  exit 1
fi

curl -s -X DELETE \
  -H 'Content-Type: application/json' \
  -d "{\"subdomain\":\"$SUBDOMAIN\"}" \
  "http://$DOMAIN/tenants"

rm -f "$BASE_DIR/vhost.d/${SUBDOMAIN}.$DOMAIN"

if [ -n "$RELOADER_URL" ]; then
  curl -s -X POST -H "X-Token: $RELOAD_TOKEN" "$RELOADER_URL"
elif [ "$NGINX_RELOAD" = "1" ]; then
  $DOCKER_COMPOSE exec "$NGINX_CONTAINER" nginx -s reload
fi
