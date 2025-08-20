#!/bin/sh
# Upgrade tenant or main container to latest image
set -e

if [ "$#" -lt 1 ]; then
  echo "Usage: $0 <tenant-slug>|--main" >&2
  exit 1
fi

if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  DOCKER_COMPOSE="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
  DOCKER_COMPOSE="docker-compose"
else
  echo "docker compose not available" >&2
  exit 1
fi

ARG="$1"
SLUG_SANITIZED="$(echo "$ARG" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9-]/-/g')"

if [ "$ARG" = "--main" ] || [ "$ARG" = "--system" ] || [ "$SLUG_SANITIZED" = "main" ]; then
  SLUG="main"
  COMPOSE_FILE="$(dirname "$0")/../docker-compose.yml"
  SERVICE="slim"
else
  SLUG="$SLUG_SANITIZED"
  TENANT_DIR="$(dirname "$0")/../tenants/$SLUG"
  COMPOSE_FILE="$TENANT_DIR/docker-compose.yml"
  SERVICE="app"
fi

if [ ! -f "$COMPOSE_FILE" ]; then
  echo "compose file not found: $COMPOSE_FILE" >&2
  exit 1
fi

if ! $DOCKER_COMPOSE -f "$COMPOSE_FILE" -p "$SLUG" pull "$SERVICE" >/dev/null 2>&1; then
  echo "pull failed" >&2
  exit 1
fi

if ! $DOCKER_COMPOSE -f "$COMPOSE_FILE" -p "$SLUG" up -d --no-deps "$SERVICE" >/dev/null 2>&1; then
  echo "upgrade failed" >&2
  exit 1
fi

printf '{"status":"upgraded","slug":"%s"}\n' "$SLUG"
