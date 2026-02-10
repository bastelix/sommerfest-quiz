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
MAIN_DOMAIN=""

if [ ! -f "$ENV_FILE" ]; then
  echo "Environment file $ENV_FILE not found" >&2
  exit 1
fi

get_env_value() {
  key="$1"
  default_value="$2"
  raw_value=$(grep -E "^${key}=" "$ENV_FILE" | tail -n 1 | cut -d '=' -f2-)
  if [ -z "$raw_value" ]; then
    printf '%s' "$default_value"
    return
  fi

  value=$(printf '%s' "$raw_value" | sed 's/[[:space:]]*#.*$//' | tr -d '\r')
  value=$(printf '%s' "$value" | sed 's/^ *//;s/ *$//;s/^"//;s/"$//')
  if [ -z "$value" ]; then
    printf '%s' "$default_value"
  else
    printf '%s' "$value"
  fi
}

DOMAIN="$(get_env_value 'DOMAIN' '')"
MAIN_DOMAIN="$(get_env_value 'MAIN_DOMAIN' '')"
BASE_PATH="$(get_env_value 'BASE_PATH' '')"
NGINX_RELOAD="$(get_env_value 'NGINX_RELOAD' '1')"
RELOADER_URL="$(get_env_value 'NGINX_RELOADER_URL' '')"
RELOAD_TOKEN="$(get_env_value 'NGINX_RELOAD_TOKEN' '')"
NGINX_CONTAINER="$(get_env_value 'NGINX_CONTAINER' 'nginx')"

API_HOST="$DOMAIN"
if [ -z "$API_HOST" ]; then
  API_HOST="$MAIN_DOMAIN"
fi

if [ -z "$API_HOST" ]; then
  echo "DOMAIN or MAIN_DOMAIN must be set in $ENV_FILE" >&2
  exit 1
fi

TENANT_DOMAIN="$MAIN_DOMAIN"
if [ -z "$TENANT_DOMAIN" ]; then
  TENANT_DOMAIN="$DOMAIN"
fi

if [ -z "$TENANT_DOMAIN" ]; then
  TENANT_DOMAIN="$API_HOST"
fi

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

if [ "$DELETE_BY_SUBDOMAIN" -eq 0 ]; then
  if [ -z "$UID" ]; then
    API_BASE="http://$API_HOST${BASE_PATH}"
    UID=$(curl -s "$API_BASE/tenants.json" | jq -r --arg sd "$SUBDOMAIN" '.tenants[] | select(.subdomain==$sd) | .uid')
  fi
  if [ -z "$UID" ] || [ "$UID" = "null" ]; then
    echo "Could not determine UID for tenant $SUBDOMAIN" >&2
    exit 1
  fi
  DATA="{\"uid\":\"$UID\"}"
else
  DATA="{\"subdomain\":\"$SUBDOMAIN\"}"
fi

API_BASE="http://$API_HOST${BASE_PATH}"

curl -s -X DELETE \
  -H 'Content-Type: application/json' \
  -d "$DATA" \
  "$API_BASE/tenants"

case "$SUBDOMAIN" in
  *.*)
    HOST_NAME=$(printf '%s' "$SUBDOMAIN" | tr '[:upper:]' '[:lower:]')
    ;;
  *)
    HOST_NAME=$(printf '%s.%s' "$SUBDOMAIN" "$TENANT_DOMAIN" | tr '[:upper:]' '[:lower:]')
    ;;
esac

rm -f "$BASE_DIR/vhost.d/$HOST_NAME"
rm -f "$BASE_DIR"/certs/"$HOST_NAME"*
rm -rf "$BASE_DIR/acme/$HOST_NAME" "$BASE_DIR/acme/${HOST_NAME}_ecc"

if [ "$NGINX_RELOAD" = "0" ]; then
  echo "NGINX_RELOAD=0 set; skipping reverse proxy reload" >&2
elif [ -n "$RELOADER_URL" ]; then
  curl -s -X POST -H "X-Token: $RELOAD_TOKEN" "$RELOADER_URL"
elif [ "$NGINX_RELOAD" = "1" ]; then
  $DOCKER_COMPOSE exec "$NGINX_CONTAINER" nginx -s reload
fi
