#!/bin/sh
# Delete a tenant and reload nginx proxy
set -e

if [ "$#" -lt 1 ] || [ "$#" -gt 2 ]; then
  echo "Usage: $0 <subdomain> [uid|--subdomain]" >&2
  exit 1
fi

SUBDOMAIN="$1"
UID=""
DELETE_BY_SUBDOMAIN=0

if [ "$#" -eq 2 ]; then
  if [ "$2" = "--subdomain" ]; then
    DELETE_BY_SUBDOMAIN=1
  else
    UID="$2"
  fi
fi
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

if [ "$DELETE_BY_SUBDOMAIN" -eq 0 ]; then
  if [ -z "$UID" ]; then
    UID=$(curl -s "http://$DOMAIN/tenants.json" | jq -r --arg sd "$SUBDOMAIN" '.tenants[] | select(.subdomain==$sd) | .uid')
  fi
  if [ -z "$UID" ] || [ "$UID" = "null" ]; then
    echo "Could not determine UID for tenant $SUBDOMAIN" >&2
    exit 1
  fi
  DATA="{\"uid\":\"$UID\"}"
else
  DATA="{\"subdomain\":\"$SUBDOMAIN\"}"
fi

curl -s -X DELETE \
  -H 'Content-Type: application/json' \
  -d "$DATA" \
  "http://$DOMAIN/tenants"

rm -f "$BASE_DIR/vhost.d/${SUBDOMAIN}.$DOMAIN"
rm -f "$BASE_DIR"/certs/"${SUBDOMAIN}.${DOMAIN}"*
rm -rf "$BASE_DIR/acme/${SUBDOMAIN}.${DOMAIN}" "$BASE_DIR/acme/${SUBDOMAIN}.${DOMAIN}_ecc"

if [ -n "$RELOADER_URL" ]; then
  curl -s -X POST -H "X-Token: $RELOAD_TOKEN" "$RELOADER_URL"
elif [ "$NGINX_RELOAD" = "1" ]; then
  $DOCKER_COMPOSE exec "$NGINX_CONTAINER" nginx -s reload
fi
