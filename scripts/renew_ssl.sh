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
RELOADER_SERVICE="${NGINX_RELOADER_SERVICE:-nginx-reloader}"

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
  SERVICE="${TENANT_SERVICE:-app}"

  if [ -f "$COMPOSE_FILE" ] && [ -n "$DOCKER_COMPOSE" ]; then
    if DETECTED_SERVICES=$($DOCKER_COMPOSE -f "$COMPOSE_FILE" config --services 2>/dev/null); then
      if echo "$DETECTED_SERVICES" | grep -qx "app"; then
        SERVICE="app"
      elif echo "$DETECTED_SERVICES" | grep -qx "slim"; then
        SERVICE="slim"
      else
        SERVICE="$(echo "$DETECTED_SERVICES" | head -n1)"
      fi
    fi
  fi

  # start tenant application container if compose file exists
  if [ -f "$COMPOSE_FILE" ] && [ -n "$DOCKER_COMPOSE" ]; then
    $DOCKER_COMPOSE -f "$COMPOSE_FILE" -p "$SLUG" up -d --no-deps "$SERVICE" >/dev/null 2>&1 || true
  fi
fi

if ! curl -fs -X POST -H "X-Token: $RELOAD_TOKEN" "$RELOADER_URL" >/dev/null; then
  if [ -n "$DOCKER_COMPOSE" ] && [ -f "$COMPOSE_FILE" ]; then
    echo "Direct nginx reload failed, retrying via $RELOADER_SERVICE container" >&2
    COMPOSE_CMD="$DOCKER_COMPOSE -f \"$COMPOSE_FILE\""
    if [ "$SLUG" != "main" ]; then
      COMPOSE_CMD="$COMPOSE_CMD -p \"$SLUG\""
    fi

    # Ensure the reload helper is running before we attempt to exec into it.
    if ! sh -c "$COMPOSE_CMD up -d --no-deps \"$RELOADER_SERVICE\"" >/dev/null 2>&1; then
      echo "Failed to start $RELOADER_SERVICE" >&2
      exit 1
    fi

    if ! sh -c "$COMPOSE_CMD exec -T \"$RELOADER_SERVICE\" curl -fs -X POST -H \"X-Token: $RELOAD_TOKEN\" http://127.0.0.1:8080/reload" >/dev/null 2>&1; then
      echo "Failed to trigger nginx reload" >&2
      exit 1
    fi
  else
    echo "Failed to trigger nginx reload" >&2
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
