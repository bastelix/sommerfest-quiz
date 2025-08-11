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
BASE_PATH="$(grep '^BASE_PATH=' "$ENV_FILE" | cut -d '=' -f2)"
SERVICE_USER="$(grep '^SERVICE_USER=' "$ENV_FILE" | cut -d '=' -f2)"
SERVICE_PASS="$(grep '^SERVICE_PASS=' "$ENV_FILE" | cut -d '=' -f2)"

[ -z "$CLIENT_MAX_BODY_SIZE" ] && CLIENT_MAX_BODY_SIZE="50m"
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
[ -z "$BASE_PATH" ] && BASE_PATH=""

if [ -z "$DOMAIN" ]; then
  echo "DOMAIN not found in $ENV_FILE" >&2
  exit 1
fi

API_BASE="http://$DOMAIN${BASE_PATH}"

if [ -z "$SERVICE_USER" ] || [ -z "$SERVICE_PASS" ]; then
  echo "SERVICE_USER or SERVICE_PASS not found in $ENV_FILE" >&2
  exit 1
fi

COOKIE_FILE=$(mktemp)

if ! curl -fs -c "$COOKIE_FILE" -X POST "$API_BASE/login" \
  -H 'Content-Type: application/json' \
  -d "{\"username\":\"$SERVICE_USER\",\"password\":\"$SERVICE_PASS\"}" >/dev/null; then
  echo "Service account login failed" >&2
  rm -f "$COOKIE_FILE"
  exit 1
fi

HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" -b "$COOKIE_FILE" -X POST \
  -H 'Content-Type: application/json' \
  -d "{\"uid\":\"$SUBDOMAIN\",\"schema\":\"$SUBDOMAIN\"}" \
  "$API_BASE/tenants")

if [ "$HTTP_STATUS" -ge 400 ]; then
  echo "Tenant creation failed with status $HTTP_STATUS" >&2
  rm -f "$COOKIE_FILE"
  exit 1
fi

mkdir -p "$BASE_DIR/vhost.d"
echo "client_max_body_size $CLIENT_MAX_BODY_SIZE;" > "$BASE_DIR/vhost.d/${SUBDOMAIN}.$DOMAIN"
if [ -n "$RELOADER_URL" ]; then
  echo "Reloading reverse proxy via $RELOADER_URL"
  if ! curl -fs -X POST -H "X-Token: $RELOAD_TOKEN" "$RELOADER_URL"; then
    echo "Proxy reload failed via webhook" >&2
    rm -f "$COOKIE_FILE"
    exit 1
  fi
elif [ "$NGINX_RELOAD" = "1" ]; then
  echo "Reloading reverse proxy via Docker"
  if ! $DOCKER_COMPOSE exec "$NGINX_CONTAINER" nginx -s reload; then
    echo "Proxy reload failed via Docker" >&2
    rm -f "$COOKIE_FILE"
    exit 1
  fi
fi

curl -fs -b "$COOKIE_FILE" -X POST "$API_BASE/api/tenants/${SUBDOMAIN}/onboard" >/dev/null || true
rm -f "$COOKIE_FILE"
