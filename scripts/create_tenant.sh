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

  # strip inline comments and surrounding quotes/whitespace
  value=$(printf '%s' "$raw_value" | sed 's/[[:space:]]*#.*$//' | tr -d '\r')
  value=$(printf '%s' "$value" | sed 's/^ *//;s/ *$//;s/^"//;s/"$//')
  if [ -z "$value" ]; then
    printf '%s' "$default_value"
  else
    printf '%s' "$value"
  fi
}

CLIENT_MAX_BODY_SIZE="$(get_env_value 'CLIENT_MAX_BODY_SIZE' '50m')"
NGINX_RELOAD="$(get_env_value 'NGINX_RELOAD' '1')"
RELOADER_URL="$(get_env_value 'NGINX_RELOADER_URL' '')"
RELOAD_TOKEN="$(get_env_value 'NGINX_RELOAD_TOKEN' '')"
NGINX_CONTAINER="$(get_env_value 'NGINX_CONTAINER' 'nginx')"
BASE_PATH="$(get_env_value 'BASE_PATH' '')"
SERVICE_USER="$(get_env_value 'SERVICE_USER' '')"
SERVICE_PASS="$(get_env_value 'SERVICE_PASS' '')"
RELOADER_SERVICE="$(get_env_value 'NGINX_RELOADER_SERVICE' 'nginx-reloader')"
DOMAIN="$(get_env_value 'DOMAIN' '')"
MAIN_DOMAIN="$(get_env_value 'MAIN_DOMAIN' '')"
TENANT_SINGLE_CONTAINER="$(get_env_value 'TENANT_SINGLE_CONTAINER' '0')"

DOCKER_COMPOSE=""

detect_docker_compose() {
  if [ -n "$DOCKER_COMPOSE" ]; then
    return
  fi

  if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
    DOCKER_COMPOSE="docker compose"
  elif command -v docker-compose >/dev/null 2>&1; then
    DOCKER_COMPOSE="docker-compose"
  else
    echo "docker compose oder docker-compose ist nicht verfügbar" >&2
    exit 1
  fi
}

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

if [ -z "$SERVICE_USER" ] || [ -z "$SERVICE_PASS" ]; then
  echo "SERVICE_USER or SERVICE_PASS not found in $ENV_FILE" >&2
  exit 1
fi

case "$SUBDOMAIN" in
  *.*)
    VHOST_NAME=$(printf '%s' "$SUBDOMAIN" | tr '[:upper:]' '[:lower:]')
    ;;
  *)
    VHOST_NAME=$(printf '%s.%s' "$SUBDOMAIN" "$TENANT_DOMAIN" | tr '[:upper:]' '[:lower:]')
    ;;
esac

if ! printf '%s' "$VHOST_NAME" | grep -Eq '^[a-z0-9-]+(\.[a-z0-9-]+)+$'; then
  echo "Generated tenant host '$VHOST_NAME' is invalid" >&2
  exit 1
fi

if [ "$TENANT_SINGLE_CONTAINER" = "1" ]; then
  BASE_HOST="$MAIN_DOMAIN"
  if [ -z "$BASE_HOST" ]; then
    BASE_HOST="$DOMAIN"
  fi

  if [ -z "$BASE_HOST" ]; then
    echo "MAIN_DOMAIN or DOMAIN must be set for single container mode" >&2
    exit 1
  fi

  case "$VHOST_NAME" in
    "$BASE_HOST"|*".$BASE_HOST")
      ;;
    *)
      echo "Tenant host '$VHOST_NAME' must be a subdomain of $BASE_HOST in single container mode" >&2
      exit 1
      ;;
  esac

  CERT_PATH="$BASE_DIR/certs/$BASE_HOST.crt"
  KEY_PATH="$BASE_DIR/certs/$BASE_HOST.key"

  if [ ! -f "$CERT_PATH" ] || [ ! -f "$KEY_PATH" ]; then
    echo "Wildcard certificate certs/$BASE_HOST.crt/.key missing; attempting automated provisioning" >&2
    if ! "$BASE_DIR/scripts/provision_wildcard.sh" --domain "$BASE_HOST" >/dev/null; then
      echo "Automatic wildcard provisioning failed. Ensure certs/$BASE_HOST.crt and certs/$BASE_HOST.key exist." >&2
      exit 1
    fi
  fi

  if [ ! -f "$CERT_PATH" ] || [ ! -f "$KEY_PATH" ]; then
    echo "Wildcard certificate certs/$BASE_HOST.crt/.key not found after provisioning attempt." >&2
    exit 1
  fi
fi

API_BASE="http://$API_HOST${BASE_PATH}"

COOKIE_FILE=""
RELOADER_TMP=""
ONBOARD_TMP=""

cleanup() {
  [ -n "$COOKIE_FILE" ] && rm -f "$COOKIE_FILE"
  [ -n "$RELOADER_TMP" ] && rm -f "$RELOADER_TMP"
  [ -n "$ONBOARD_TMP" ] && rm -f "$ONBOARD_TMP"
}

trap cleanup EXIT

COOKIE_FILE=$(mktemp)

if ! curl -fs -c "$COOKIE_FILE" -X POST "$API_BASE/login" \
  -H 'Content-Type: application/json' \
  -d "{\"username\":\"$SERVICE_USER\",\"password\":\"$SERVICE_PASS\"}" >/dev/null; then
  echo "Service account login failed" >&2
  exit 1
fi

HTTP_STATUS=$(curl -s -o /dev/null -w "%{http_code}" -b "$COOKIE_FILE" -X POST \
  -H 'Content-Type: application/json' \
  -d "{\"uid\":\"$SUBDOMAIN\",\"schema\":\"$SUBDOMAIN\"}" \
  "$API_BASE/tenants")

if [ "$HTTP_STATUS" -ge 400 ]; then
  echo "Tenant creation failed with status $HTTP_STATUS" >&2
  exit 1
fi

mkdir -p "$BASE_DIR/vhost.d"
echo "client_max_body_size $CLIENT_MAX_BODY_SIZE;" > "$BASE_DIR/vhost.d/$VHOST_NAME"

if [ -n "$RELOADER_URL" ]; then
  detect_docker_compose
  echo "Ensuring nginx-reloader service is running"
  if ! $DOCKER_COMPOSE up -d "$RELOADER_SERVICE" >/dev/null 2>&1; then
    echo "nginx-reloader service could not be started" >&2
    exit 1
  fi
  echo "Reloading reverse proxy via $RELOADER_URL"
  RELOADER_TMP=$(mktemp)
  HTTP_CODE=$(curl -s -o "$RELOADER_TMP" -w "%{http_code}" -X POST \
    -H "X-Token: $RELOAD_TOKEN" "$RELOADER_URL") || HTTP_CODE=000
  if [ "$HTTP_CODE" -ge 400 ] || [ "$HTTP_CODE" -eq 000 ]; then
    echo "Proxy reload failed via webhook (status $HTTP_CODE): $(cat "$RELOADER_TMP" 2>/dev/null)" >&2
    exit 1
  fi
  rm -f "$RELOADER_TMP"
  RELOADER_TMP=""
elif [ "$NGINX_RELOAD" = "1" ]; then
  detect_docker_compose
  echo "Reloading reverse proxy via Docker"
  if ! $DOCKER_COMPOSE exec "$NGINX_CONTAINER" nginx -s reload; then
    echo "Proxy reload failed via Docker" >&2
    exit 1
  fi
fi

ONBOARD_TMP=$(mktemp)
HTTP_STATUS=$(curl -s -o "$ONBOARD_TMP" -w "%{http_code}" -b "$COOKIE_FILE" -X POST \
  "$API_BASE/api/tenants/${SUBDOMAIN}/onboard")
CURL_EXIT=$?
if [ "$CURL_EXIT" -ne 0 ] || [ "$HTTP_STATUS" -lt 200 ] || [ "$HTTP_STATUS" -ge 300 ]; then
  echo "Tenant onboarding failed (status $HTTP_STATUS): $(cat "$ONBOARD_TMP" 2>/dev/null)" >&2
  exit 1
fi
rm -f "$ONBOARD_TMP"
ONBOARD_TMP=""
rm -f "$COOKIE_FILE"
COOKIE_FILE=""
