#!/bin/sh
# Upgrade tenant or main container using locally built image
set -e

usage() {
  echo "Usage: $0 [--verbose] [--no-pull] <tenant-slug>|--main" >&2
  exit 1
}

VERBOSE=0
PULL=1
ARG=""
while [ $# -gt 0 ]; do
  case "$1" in
    -v|--verbose)
      VERBOSE=1
      ;;
    --no-pull)
      PULL=0
      ;;
    -*)
      usage
      ;;
    *)
      [ -n "$ARG" ] && usage
      ARG="$1"
      ;;
  esac
  shift
done

[ -z "$ARG" ] && usage

SCRIPT_DIR="$(dirname "$0")"

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
  COMPOSE_FILE="$SCRIPT_DIR/../docker-compose.yml"
  SERVICE="slim"
else
  SLUG="$SLUG_SANITIZED"
  TENANTS_DIR="${TENANTS_DIR:-$SCRIPT_DIR/../tenants}"
  TENANT_DIR="$TENANTS_DIR/$SLUG"
  COMPOSE_FILE="$TENANT_DIR/docker-compose.yml"
  SERVICE="app"
fi

if [ ! -f "$COMPOSE_FILE" ]; then
  echo "compose file not found: $COMPOSE_FILE" >&2
  exit 1
fi

IMAGE="${APP_IMAGE:-sommerfest-quiz:latest}"
if [ "$SERVICE" = "app" ]; then
  if ! sed -i.bak "0,/^[[:space:]]*image:/s#^[[:space:]]*image:.*#  image: $IMAGE#" "$COMPOSE_FILE"; then
    echo "failed to update image" >&2
    exit 1
  fi
  rm -f "$COMPOSE_FILE.bak"
fi

run() {
  if [ "$VERBOSE" -eq 1 ]; then
    "$@"
  else
    "$@" >/dev/null 2>&1
  fi
}

if [ "$PULL" -eq 1 ]; then
  if ! run $DOCKER_COMPOSE -f "$COMPOSE_FILE" -p "$SLUG" pull "$SERVICE"; then
    echo "pull failed, continuing with existing image" >&2
  fi
fi

if ! run $DOCKER_COMPOSE -f "$COMPOSE_FILE" -p "$SLUG" up -d --no-deps --force-recreate "$SERVICE"; then
  echo "upgrade failed" >&2
  exit 1
fi

printf '{"status":"upgraded","slug":"%s"}\n' "$SLUG"
