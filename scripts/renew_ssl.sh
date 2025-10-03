#!/bin/sh
# Force renew SSL certificate for a tenant or the main system via acme-companion
set -e

SCRIPT_DIR="$(dirname "$0")"
ENV_FILE="${ENV_FILE:-$SCRIPT_DIR/../.env}"

# Preserve manually exported values before loading .env defaults
ORIG_RELOAD_TOKEN="${NGINX_RELOAD_TOKEN-}"
ORIG_RELOADER_URL="${NGINX_RELOADER_URL-}"
ORIG_NGINX_CONTAINER="${NGINX_CONTAINER-}"

if [ -f "$ENV_FILE" ]; then
  # shellcheck disable=SC1090
  . "$ENV_FILE"
fi

# Restore explicitly exported values so we do not override the caller's environment
if [ -n "$ORIG_RELOAD_TOKEN" ]; then
  NGINX_RELOAD_TOKEN="$ORIG_RELOAD_TOKEN"
fi

if [ -n "$ORIG_RELOADER_URL" ]; then
  NGINX_RELOADER_URL="$ORIG_RELOADER_URL"
fi

if [ -n "$ORIG_NGINX_CONTAINER" ]; then
  NGINX_CONTAINER="$ORIG_NGINX_CONTAINER"
fi

if [ "$#" -lt 1 ]; then
  echo "Usage: $0 <tenant-slug>|--main" >&2
  exit 1
fi

if [ "${NGINX_RELOADER_URL+x}" = "x" ]; then
  RELOADER_URL="$NGINX_RELOADER_URL"
else
  RELOADER_URL="http://nginx-reloader:8080/reload"
fi

if [ "${NGINX_RELOAD_TOKEN+x}" = "x" ]; then
  RELOAD_TOKEN="$NGINX_RELOAD_TOKEN"
else
  RELOAD_TOKEN="changeme"
fi

if [ "${NGINX_CONTAINER+x}" = "x" ]; then
  RELOAD_TARGET="$NGINX_CONTAINER"
else
  RELOAD_TARGET="nginx"
fi

if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  DOCKER_COMPOSE="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
  DOCKER_COMPOSE="docker-compose"
else
  DOCKER_COMPOSE=""
fi

if [ "$1" = "--main" ] || [ "$1" = "--system" ]; then
  SLUG="main"
  COMPOSE_FILE="$SCRIPT_DIR/../docker-compose.yml"
  SERVICE="slim"
else
  SLUG="$(echo "$1" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9-]/-/g')"
  TENANTS_DIR="${TENANTS_DIR:-$SCRIPT_DIR/../tenants}"
  TENANT_DIR="$TENANTS_DIR/$SLUG"
  COMPOSE_FILE="$TENANT_DIR/docker-compose.yml"
  SERVICE="slim"

  # start tenant application container if compose file exists
  if [ -f "$COMPOSE_FILE" ] && [ -n "$DOCKER_COMPOSE" ]; then
    $DOCKER_COMPOSE -f "$COMPOSE_FILE" -p "$SLUG" up -d --no-deps "$SERVICE" >/dev/null 2>&1 || true
  fi
fi

RELOAD_SUCCESS=0

if [ -n "$RELOADER_URL" ]; then
  HTTP_STATUS=$(curl -s -o /dev/null -w '%{http_code}' -X POST -H "X-Token: $RELOAD_TOKEN" "$RELOADER_URL" || true)
  if [ "$HTTP_STATUS" = "200" ]; then
    RELOAD_SUCCESS=1
  elif [ -n "$HTTP_STATUS" ]; then
    echo "nginx reload webhook responded with HTTP $HTTP_STATUS, falling back to local docker exec" >&2
  else
    echo "nginx reload webhook unreachable, falling back to local docker exec" >&2
  fi
else
  echo "nginx reload webhook disabled, falling back to local docker exec" >&2
fi

if [ "$RELOAD_SUCCESS" -eq 0 ]; then
  if ! command -v docker >/dev/null 2>&1; then
    echo "Failed to trigger nginx reload: docker CLI not available for local fallback" >&2
    exit 1
  fi

  if ! docker ps --format '{{.Names}}' | grep -Fxq "$RELOAD_TARGET"; then
    echo "Failed to trigger nginx reload: container '$RELOAD_TARGET' not found" >&2
    exit 1
  fi

  if ! docker exec "$RELOAD_TARGET" nginx -s reload >/dev/null 2>&1; then
    echo "Failed to trigger nginx reload via docker exec" >&2
    exit 1
  fi
fi

if [ -z "$DOCKER_COMPOSE" ]; then
  echo "docker compose not available" >&2
  exit 1
fi

if [ "$SLUG" = "main" ]; then
  if ! $DOCKER_COMPOSE -f "$COMPOSE_FILE" restart "$SERVICE" >/dev/null; then
    echo "Failed to restart main services" >&2
    exit 1
  fi
else
  if ! $DOCKER_COMPOSE -f "$COMPOSE_FILE" -p "$SLUG" restart "$SERVICE" --no-deps >/dev/null; then
    echo "Failed to restart tenant application" >&2
    exit 1
  fi
fi

printf '{"status":"renewed","slug":"%s"}\n' "$SLUG"
