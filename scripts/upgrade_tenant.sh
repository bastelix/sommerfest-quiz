#!/bin/sh
# Upgrade tenant or main container using locally built image
set -e

IMAGE_TAG=""
ARG=""

while [ $# -gt 0 ]; do
  case "$1" in
    --image)
      if [ -n "$2" ]; then
        IMAGE_TAG="$2"
        shift 2
      else
        echo "--image requires a tag" >&2
        exit 1
      fi
      ;;
    *)
      if [ -n "$ARG" ]; then
        echo "Usage: $0 <tenant-slug>|--main [--image <tag>]" >&2
        exit 1
      fi
      ARG="$1"
      shift
      ;;
  esac
done

if [ -z "$ARG" ]; then
  echo "Usage: $0 <tenant-slug>|--main [--image <tag>]" >&2
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

if [ -n "$IMAGE_TAG" ] && [ "$SLUG" != "main" ]; then
  if ! sed -i "0,/^[[:space:]]*image:/s#^[[:space:]]*image:.*#  image: $IMAGE_TAG#" "$COMPOSE_FILE"; then
    echo "failed to update image tag" >&2
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
