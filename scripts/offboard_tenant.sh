#!/bin/sh
# Stop and remove a tenant container and clean up resources
set -e

SCRIPT_DIR="$(dirname "$0")"

if [ "$#" -lt 1 ]; then
  echo "Usage: $0 <tenant-slug>" >&2
  exit 1
fi

SLUG=$(echo "$1" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9-]/-/g')
TENANTS_DIR="${TENANTS_DIR:-$SCRIPT_DIR/../tenants}"
TENANT_DIR="$TENANTS_DIR/$SLUG"
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

if [ -f "$COMPOSE_FILE" ]; then
  $DOCKER_COMPOSE -f "$COMPOSE_FILE" -p "$SLUG" down -v || true
else
  echo "skip down: $COMPOSE_FILE missing"
fi

rm -rf "$TENANT_DIR"
printf '{"status":"removed","slug":"%s"}\n' "$SLUG"
