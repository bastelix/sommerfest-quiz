#!/bin/sh
# Upgrade tenant or main container using locally built image
set -e

if [ $# -ne 1 ]; then
  echo "Usage: $0 <tenant-slug>|--main" >&2
  exit 1
fi

ARG="$1"

if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  DOCKER_COMPOSE="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
  DOCKER_COMPOSE="docker-compose"
else
  echo "docker compose not available" >&2
  exit 1
fi

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

IMAGE="${APP_IMAGE:-sommerfest-quiz:latest}"
if [ "$SERVICE" = "app" ]; then
  if ! sed -i "0,/^[[:space:]]*image:/s#^[[:space:]]*image:.*#  image: $IMAGE#" "$COMPOSE_FILE"; then
    echo "failed to update image" >&2
    exit 1
  fi
fi

if ! $DOCKER_COMPOSE -f "$COMPOSE_FILE" -p "$SLUG" pull "$SERVICE" >/dev/null 2>&1; then
  echo "pull failed" >&2
  exit 1
fi

if ! $DOCKER_COMPOSE -f "$COMPOSE_FILE" -p "$SLUG" up -d --no-deps --force-recreate "$SERVICE" >/dev/null 2>&1; then
  echo "upgrade failed" >&2
  exit 1
fi

printf '{"status":"upgraded","slug":"%s"}\n' "$SLUG"
