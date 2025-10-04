#!/bin/sh
# Force renew SSL certificate for a tenant or the main system via acme-companion
set -e

SCRIPT_DIR="$(dirname "$0")"

load_env_defaults() {
  file="$1"
  shift

  [ -f "$file" ] || return 0

  while [ "$#" -gt 0 ]; do
    var="$1"
    fallback="$2"
    shift 2

    if [ -n "${!var+x}" ]; then
      continue
    fi

    raw_value=$(grep -E "^${var}=" "$file" | tail -n1 | cut -d '=' -f2-)
    if [ -z "$raw_value" ]; then
      if [ -n "$fallback" ]; then
        export "$var=$fallback"
      fi
      continue
    fi

    value=$(printf '%s' "$raw_value" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
    if [ -n "$value" ]; then
      first_char=${value%${value#?}}
      last_char=${value#${value%?}}
    else
      first_char=""
      last_char=""
    fi

    if [ "$first_char" = '"' ] && [ "$last_char" = '"' ]; then
      value=${value#?}
      value=${value%?}
    elif [ "$first_char" = "'" ] && [ "$last_char" = "'" ]; then
      value=${value#?}
      value=${value%?}
    else
      value=$(printf '%s' "$value" | sed 's/[[:space:]]*#.*$//')
      value=$(printf '%s' "$value" | sed 's/[[:space:]]*$//')
    fi

    export "$var=$value"
  done
}

load_env_defaults "$SCRIPT_DIR/../.env" \
  TENANTS_DIR "" \
  NGINX_RELOAD "" \
  NGINX_RELOADER_URL "" \
  NGINX_RELOAD_TOKEN "" \
  NGINX_RELOADER_SERVICE "" \
  NGINX_CONTAINER ""

if [ "$#" -lt 1 ]; then
  echo "Usage: $0 <tenant-slug>|--main" >&2
  exit 1
fi

if [ -z "${TENANTS_DIR+x}" ] || [ -z "$TENANTS_DIR" ]; then
  TENANTS_DIR="$SCRIPT_DIR/../tenants"
fi

if [ -n "${NGINX_RELOAD+x}" ]; then
  RELOAD_FLAG="$NGINX_RELOAD"
else
  RELOAD_FLAG="1"
fi

if [ -n "${NGINX_RELOADER_URL+x}" ]; then
  RELOADER_URL="$NGINX_RELOADER_URL"
else
  RELOADER_URL="http://nginx-reloader:8080/reload"
fi

if [ -n "${NGINX_RELOAD_TOKEN+x}" ]; then
  RELOAD_TOKEN="$NGINX_RELOAD_TOKEN"
else
  RELOAD_TOKEN="changeme"
fi

if [ -n "${NGINX_RELOADER_SERVICE+x}" ]; then
  RELOADER_SERVICE="$NGINX_RELOADER_SERVICE"
else
  RELOADER_SERVICE="nginx-reloader"
fi

if [ -n "${NGINX_CONTAINER+x}" ]; then
  NGINX_CONTAINER_NAME="$NGINX_CONTAINER"
else
  NGINX_CONTAINER_NAME="nginx"
fi

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

  if [ -f "$COMPOSE_FILE" ] && [ -n "$DOCKER_COMPOSE" ]; then
    $DOCKER_COMPOSE -f "$COMPOSE_FILE" -p "$SLUG" up -d --no-deps "$SERVICE" >/dev/null 2>&1 || true
  fi
fi

compose_cmd() {
  if [ -z "$DOCKER_COMPOSE" ] || [ ! -f "$COMPOSE_FILE" ]; then
    return 1
  fi

  if [ "$SLUG" = "main" ]; then
    $DOCKER_COMPOSE -f "$COMPOSE_FILE" "$@"
  else
    $DOCKER_COMPOSE -f "$COMPOSE_FILE" -p "$SLUG" "$@"
  fi
}

reload_via_webhook() {
  if [ -z "$RELOADER_URL" ]; then
    return 2
  fi

  case "$RELOADER_URL" in
    http://nginx-reloader:*|https://nginx-reloader:*)
      compose_cmd up -d --no-deps "$RELOADER_SERVICE" >/dev/null 2>&1 || true
      ;;
  esac

  tmp_file=$(mktemp)
  http_code=$(curl -s -o "$tmp_file" -w "%{http_code}" -X POST -H "X-Token: $RELOAD_TOKEN" "$RELOADER_URL") || http_code=000

  if [ "$http_code" -ge 200 ] && [ "$http_code" -lt 300 ]; then
    rm -f "$tmp_file"
    return 0
  fi

  if [ "$http_code" -eq 000 ]; then
    echo "nginx reload webhook unreachable at $RELOADER_URL" >&2
  else
    body=$(cat "$tmp_file" 2>/dev/null)
    if [ -n "$body" ]; then
      echo "nginx reload webhook returned status $http_code: $body" >&2
    else
      echo "nginx reload webhook returned status $http_code" >&2
    fi
  fi

  rm -f "$tmp_file"
  return 1
}

reload_via_docker_exec() {
  if ! command -v docker >/dev/null 2>&1; then
    echo "docker command not available for nginx reload fallback" >&2
    return 1
  fi

  if ! docker inspect -f '{{.State.Running}}' "$NGINX_CONTAINER_NAME" >/dev/null 2>&1; then
    echo "nginx container '$NGINX_CONTAINER_NAME' is not running" >&2
    return 1
  fi

  if ! docker exec "$NGINX_CONTAINER_NAME" nginx -s reload >/dev/null 2>&1; then
    echo "Failed to reload nginx inside container '$NGINX_CONTAINER_NAME' via docker exec" >&2
    return 1
  fi

  return 0
}

WEBHOOK_RESULT=0
WEBHOOK_RESULT=$(reload_via_webhook) || WEBHOOK_RESULT=$?

if [ "$WEBHOOK_RESULT" -eq 2 ]; then
  if [ "$RELOAD_FLAG" = "0" ]; then
    echo "nginx reload webhook disabled in configuration; falling back to docker exec because NGINX_RELOAD=0" >&2
  else
    echo "nginx reload webhook disabled, falling back to docker exec" >&2
  fi
elif [ "$WEBHOOK_RESULT" -ne 0 ]; then
  echo "Falling back to docker exec for nginx reload" >&2
fi

if [ "$WEBHOOK_RESULT" -ne 0 ]; then
  if ! reload_via_docker_exec; then
    echo "Failed to trigger nginx reload via webhook or docker exec" >&2
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
