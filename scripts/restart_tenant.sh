#!/bin/sh
# Restart the main application container
set -e

SCRIPT_DIR="$(dirname "$0")"

if [ "$#" -lt 1 ]; then
  echo "Usage: $0 --main" >&2
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

if [ "$1" = "--main" ] || [ "$1" = "--system" ]; then
  SLUG="main"
  COMPOSE_FILE="$SCRIPT_DIR/../docker-compose.yml"
  SERVICE="slim"
else
  echo "Only --main is supported. Per-tenant containers have been removed." >&2
  exit 1
fi

if [ ! -f "$COMPOSE_FILE" ]; then
  echo "compose file not found" >&2
  exit 1
fi

if ! $DOCKER_COMPOSE -f "$COMPOSE_FILE" -p "$SLUG" restart "$SERVICE" >/dev/null 2>&1; then
  echo "restart failed" >&2
  exit 1
fi

printf '{"status":"restarted","slug":"%s"}\n' "$SLUG"
