#!/bin/sh
# Force renew SSL certificate for a tenant or the main system via Traefik
set -e

SCRIPT_DIR="$(dirname "$0")"
ROOT_COMPOSE_FILE="$SCRIPT_DIR/../docker-compose.yml"

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
  NGINX_CONTAINER "" \
  DOMAIN "" \
  MAIN_DOMAIN "" \
  TRAEFIK_ACME_API_ENDPOINT ""

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

TLS_DOMAIN=""

if [ "$1" = "--main" ] || [ "$1" = "--system" ]; then
  SLUG="main"
  COMPOSE_FILE="$SCRIPT_DIR/../docker-compose.yml"
  SERVICE="slim"
  if [ -n "${MAIN_DOMAIN+x}" ] && [ -n "$MAIN_DOMAIN" ]; then
    TLS_DOMAIN="$MAIN_DOMAIN"
  elif [ -n "${DOMAIN+x}" ] && [ -n "$DOMAIN" ]; then
    TLS_DOMAIN="$DOMAIN"
  fi
else
  SLUG="$(echo "$1" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9-]/-/g')"
  TENANT_DIR="$TENANTS_DIR/$SLUG"
  COMPOSE_FILE="$TENANT_DIR/docker-compose.yml"
  SERVICE="${TENANT_SERVICE:-app}"

  DOMAIN_SUFFIX="$MAIN_DOMAIN"
  if [ -z "$DOMAIN_SUFFIX" ]; then
    DOMAIN_SUFFIX="$DOMAIN"
  fi
  TLS_DOMAIN="$DOMAIN_SUFFIX"

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

TRAEFIK_API_ENDPOINT="${TRAEFIK_ACME_API_ENDPOINT:-http://traefik:8080}"

traefik_api_request() {
  method="$1"
  path="$2"
  shift 2 || true
  if [ $# -gt 0 ]; then
    body="$1"
  else
    body=""
  fi

  if [ "$method" = "GET" ]; then
    curl -fsS "$TRAEFIK_API_ENDPOINT$path"
  else
    curl -fsS -X "$method" "$TRAEFIK_API_ENDPOINT$path" -H 'Content-Type: application/json' -d "$body"
  fi
}

traefik_certificate_status() {
  domain="$1"
  set +e
  response=$(traefik_api_request GET /api/tls/certificates)
  status=$?
  set -e
  if [ $status -ne 0 ] || [ -z "$response" ]; then
    echo "ERROR"
    return
  fi

  printf '%s' "$response" | python3 <<'PY' "$domain"
import json
import sys

domain = sys.argv[1]
payload = sys.stdin.read().strip()
if not payload:
    print("ERROR\tno-data")
    raise SystemExit

try:
    data = json.loads(payload)
except json.JSONDecodeError:
    print("ERROR\tinvalid-json")
    raise SystemExit

if isinstance(data, dict):
    certificates = data.get("certificates") or data.get("data", {}).get("certificates")
    if certificates is None and "value" in data:
        value = data["value"]
        if isinstance(value, dict):
            certificates = value.get("certificates")
    if certificates is None:
        certificates = []
else:
    certificates = data

target_san = f"*.{domain}"

for cert in certificates:
    dom = cert.get("domain") or {}
    main = dom.get("main") or dom.get("Main")
    sans = dom.get("sans") or dom.get("Sans") or []
    if not isinstance(sans, list):
        sans = []
    if main == domain or target_san in sans or f"*.{main}" == target_san:
        not_after = cert.get("notAfter") or cert.get("NotAfter") or cert.get("expiry") or ""
        resolver = cert.get("issuer") or cert.get("resolver") or ""
        print(f"FOUND\t{not_after}\t{resolver}")
        break
else:
    print("MISSING\t\t")
PY
}

ensure_traefik_running() {
  if [ -z "$DOCKER_COMPOSE" ] || [ ! -f "$ROOT_COMPOSE_FILE" ]; then
    return 1
  fi

  if ! $DOCKER_COMPOSE -f "$ROOT_COMPOSE_FILE" ps -q traefik >/dev/null 2>&1 || [ -z "$($DOCKER_COMPOSE -f "$ROOT_COMPOSE_FILE" ps -q traefik 2>/dev/null)" ]; then
    $DOCKER_COMPOSE -f "$ROOT_COMPOSE_FILE" up -d traefik >/dev/null 2>&1 || return 1
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

CERT_STATUS="skipped"
CERT_EXPIRES=""
CERT_DOMAIN="$TLS_DOMAIN"

if [ -n "$TLS_DOMAIN" ]; then
  if ensure_traefik_running; then
    set +e
    traefik_api_request POST /api/providers/reload ""
    reload_code=$?
    set -e
    if [ $reload_code -ne 0 ]; then
      echo "Failed to trigger Traefik provider reload via API" >&2
      CERT_STATUS="reload-failed"
    else
      attempt=0
      max_attempts=12
      while [ $attempt -lt $max_attempts ]; do
        status_line=$(traefik_certificate_status "$TLS_DOMAIN")
        status=$(printf '%s' "$status_line" | cut -f1)
        expiry=$(printf '%s' "$status_line" | cut -f2)
        if [ "$status" = "FOUND" ]; then
          CERT_STATUS="ready"
          CERT_EXPIRES="$expiry"
          if [ -n "$CERT_EXPIRES" ]; then
            echo "Traefik certificate for $TLS_DOMAIN valid until $CERT_EXPIRES" >&2
          else
            echo "Traefik certificate for $TLS_DOMAIN confirmed" >&2
          fi
          break
        elif [ "$status" = "ERROR" ]; then
          CERT_STATUS="api-error"
          echo "Traefik API returned an error while checking certificates" >&2
          break
        else
          CERT_STATUS="pending"
        fi
        sleep 5
        attempt=$((attempt + 1))
      done
      if [ "$CERT_STATUS" = "pending" ]; then
        CERT_STATUS="timeout"
        echo "Traefik did not report the expected certificate for $TLS_DOMAIN in time" >&2
      fi
    fi
  else
    echo "Unable to ensure Traefik container is running" >&2
    CERT_STATUS="unavailable"
  fi
fi

printf '{"status":"renewed","slug":"%s","tls_domain":"%s","certificate_status":"%s","expires":"%s"}\n' \
  "$SLUG" "${CERT_DOMAIN:-}" "${CERT_STATUS:-}" "${CERT_EXPIRES:-}"
