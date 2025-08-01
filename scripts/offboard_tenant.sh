#!/bin/sh
# Stop and remove a tenant container and clean up resources
set -e

if [ "$#" -lt 1 ]; then
  echo "Usage: $0 <tenant-slug>" >&2
  exit 1
fi

SLUG=$(echo "$1" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9-]/-/g')
TENANT_DIR="$(dirname "$0")/../tenants/$SLUG"
COMPOSE_FILE="$TENANT_DIR/docker-compose.yml"

# detect docker compose command
if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  DOCKER_COMPOSE="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
  DOCKER_COMPOSE="docker-compose"
else
  echo "docker compose oder docker-compose ist nicht verf\u00fcgbar" >&2
  exit 1
fi

if [ ! -f "$COMPOSE_FILE" ]; then
  echo "Compose file '$COMPOSE_FILE' not found" >&2
else
  $DOCKER_COMPOSE -f "$COMPOSE_FILE" -p "$SLUG" down -v || true
fi

rm -rf "$TENANT_DIR"

printf '{"status":"removed","slug":"%s"}\n' "$SLUG"
