#!/bin/sh
# Force renew SSL certificate for a tenant or the main system via acme-companion
set -e

SCRIPT_DIR="$(dirname "$0")"

if [ "$#" -lt 1 ]; then
  echo "Usage: $0 <tenant-slug>|--main" >&2
  exit 1
fi

RELOADER_URL="${NGINX_RELOADER_URL:-http://nginx-reloader:8080/reload}"
RELOAD_TOKEN="${NGINX_RELOAD_TOKEN:-changeme}"

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

if ! curl -fs -X POST -H "X-Token: $RELOAD_TOKEN" "$RELOADER_URL" >/dev/null; then
  echo "Failed to trigger nginx reload" >&2
  exit 1
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
