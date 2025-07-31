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

if [ ! -f "$COMPOSE_FILE" ]; then
  echo "Compose file '$COMPOSE_FILE' not found" >&2
else
  docker compose -f "$COMPOSE_FILE" -p "$SLUG" down -v || true
fi

rm -rf "$TENANT_DIR"

printf '{"status":"removed","slug":"%s"}\n' "$SLUG"
