#!/bin/sh
# Force renew SSL certificate for a tenant via acme-companion
set -e

if [ "$#" -lt 1 ]; then
  echo "Usage: $0 <tenant-slug>" >&2
  exit 1
fi

SLUG="$(echo "$1" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9-]/-/g')"
TENANT_DIR="$(dirname "$0")/../tenants/$SLUG"
COMPOSE_FILE="$TENANT_DIR/docker-compose.yml"
RELOADER_URL="${NGINX_RELOADER_URL:-http://nginx-reloader:8080/reload}"
RELOAD_TOKEN="${NGINX_RELOAD_TOKEN:-changeme}"

# start tenant container if compose file exists
if [ -f "$COMPOSE_FILE" ]; then
  if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
    DOCKER_COMPOSE="docker compose"
  elif command -v docker-compose >/dev/null 2>&1; then
    DOCKER_COMPOSE="docker-compose"
  else
    DOCKER_COMPOSE=""
  fi
  if [ -n "$DOCKER_COMPOSE" ]; then
    $DOCKER_COMPOSE -f "$COMPOSE_FILE" -p "$SLUG" up -d >/dev/null 2>&1 || true
  fi
fi

if ! curl -fs -X POST -H "X-Token: $RELOAD_TOKEN" "$RELOADER_URL" >/dev/null; then
  echo "Failed to trigger nginx reload" >&2
  exit 1
fi

if ! docker compose -f "$COMPOSE_FILE" -p "$SLUG" restart >/dev/null; then
  echo "Failed to restart tenant services" >&2
  exit 1
fi

printf '{"status":"renewed","slug":"%s"}\n' "$SLUG"
