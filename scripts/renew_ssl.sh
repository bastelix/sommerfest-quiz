#!/bin/sh
# Force renew SSL certificate for a tenant or the main system via acme-companion
set -e

SCRIPT_DIR="$(dirname "$0")"

if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  DOCKER_COMPOSE="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
  DOCKER_COMPOSE="docker-compose"
else
  echo "docker compose not available" >&2
  exit 1
fi

load_env_defaults() {
  file="$1"
  shift

  [ -f "$file" ] || return 0

  while [ "$#" -gt 0 ]; do
    var="$1"
    fallback="$2"
    shift 2

    eval "is_set=\${$var+x}"
    if [ -n "$is_set" ]; then
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
  MAIN_DOMAIN "" \
  DOMAIN "" \
  DOMAIN_STRIPPED_PREFIXES "" \
  MARKETING_DOMAINS "" \
  SLIM_LETSENCRYPT_HOST "" \
  SLIM_VIRTUAL_HOST "" \
  LETSENCRYPT_HOST "" \
  ACME_COMPANION_CONTAINER "" \
  SSL_CERT_WAIT_SECONDS "" \
  SSL_CERT_POLL_INTERVAL_SECONDS ""

build_alias_list() {
  config=$(printf '%s' "${DOMAIN_STRIPPED_PREFIXES:-}" | tr '[:upper:]' '[:lower:]')
  config=$(printf '%s' "$config" | tr '\t,\n' '   ')

  if [ -n "$config" ]; then
    aliases="www"
    for token in $config; do
      token=$(printf '%s' "$token" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
      if [ -z "$token" ]; then
        continue
      fi
      case " $aliases " in
        *" $token "*)
          ;;
        *)
          aliases="$aliases $token"
          ;;
      esac
    done
  else
    aliases="www admin assistant"
  fi

  printf '%s' "$aliases"
}

BASE_DOMAIN=""
if [ -n "${MAIN_DOMAIN+x}" ] && [ -n "$MAIN_DOMAIN" ]; then
  BASE_DOMAIN=$(printf '%s' "$MAIN_DOMAIN" | tr '[:upper:]' '[:lower:]')
elif [ -n "${DOMAIN+x}" ] && [ -n "$DOMAIN" ]; then
  BASE_DOMAIN=$(printf '%s' "$DOMAIN" | tr '[:upper:]' '[:lower:]')
fi

MAIN_ALIAS_LIST="$(build_alias_list)"
CERT_DIR="$SCRIPT_DIR/../certs"

trim() {
  printf '%s' "$1" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//'
}

normalize_csv_list() {
  raw=$(printf '%s' "$1" | tr '\n' ',')
  printf '%s' "$raw" | tr '\t ' ',' | sed 's/,,*/,/g; s/^,//; s/,$//'
}

is_main_alias() {
  candidate=$(printf '%s' "$1" | tr '[:upper:]' '[:lower:]')
  for alias in $MAIN_ALIAS_LIST; do
    if [ "$alias" = "$candidate" ]; then
      return 0
    fi
  done

  return 1
}

resolve_slug() {
  input="$1"
  lowered=$(printf '%s' "$input" | tr '[:upper:]' '[:lower:]')
  trimmed=$(printf '%s' "$lowered" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
  host=$(printf '%s' "$trimmed" | sed -E 's#^[a-z0-9+.-]+://##')
  host=${host%%/*}
  host=${host%%\?*}
  host=${host%%#*}
  host=${host%%:*}
  host=$(printf '%s' "$host" | tr -cd 'abcdefghijklmnopqrstuvwxyz0123456789.-')

  if [ -n "$host" ] && [ -n "$BASE_DOMAIN" ]; then
    if [ "$host" = "$BASE_DOMAIN" ]; then
      printf 'main'
      return
    fi

    suffix=".$BASE_DOMAIN"
    case "$host" in
      *"$suffix")
        prefix=${host%$suffix}
        prefix=${prefix%.}
        if [ -z "$prefix" ]; then
          printf 'main'
          return
        fi

        first_label=${prefix%%.*}
        if [ -z "$first_label" ]; then
          first_label="$prefix"
        fi

        if is_main_alias "$first_label"; then
          printf 'main'
          return
        fi

        slug=$(printf '%s' "$prefix" | tr '.:/_' '-' | sed 's/[^a-z0-9-]/-/g')
        slug=$(printf '%s' "$slug" | sed 's/--*/-/g;s/^-//;s/-$//')
        if [ -z "$slug" ]; then
          printf 'main'
        else
          printf '%s' "$slug"
        fi
        return
        ;;
    esac
  fi

  fallback=$(printf '%s' "$input" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9-]/-/g')
  fallback=$(printf '%s' "$fallback" | sed 's/--*/-/g;s/^-//;s/-$//')

  if [ -z "$fallback" ]; then
    printf '%s' "$fallback"
    return
  fi

  if [ -n "$BASE_DOMAIN" ] && is_main_alias "$fallback"; then
    printf 'main'
  else
    printf '%s' "$fallback"
  fi
}

usage() {
  cat >&2 <<'EOF'
Usage: scripts/renew_ssl.sh [--recreate] <tenant-slug>|--main

Options:
  --recreate   Recreate the target service instead of issuing a plain restart.
               Use this when environment variables such as MARKETING_DOMAINS or
               LETSENCRYPT_HOST have changed and the container must pick up the
               new values so that docker-gen and acme-companion discover the
               updated domain list.
EOF
  exit 1
}

RECREATE="0"

while [ "$#" -gt 0 ]; do
  case "$1" in
    --recreate)
      RECREATE="1"
      shift
      ;;
    --help|-h)
      usage
      ;;
    *)
      break
      ;;
  esac
done

if [ "$#" -lt 1 ]; then
  usage
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

if [ -n "${ACME_COMPANION_CONTAINER+x}" ]; then
  ACME_CONTAINER_NAME="$ACME_COMPANION_CONTAINER"
else
  ACME_CONTAINER_NAME="acme-companion"
fi

if [ -n "${SSL_CERT_WAIT_SECONDS+x}" ] && [ -n "$SSL_CERT_WAIT_SECONDS" ]; then
  CERT_WAIT_SECONDS="$SSL_CERT_WAIT_SECONDS"
else
  CERT_WAIT_SECONDS="60"
fi

if [ -n "${SSL_CERT_POLL_INTERVAL_SECONDS+x}" ] && [ -n "$SSL_CERT_POLL_INTERVAL_SECONDS" ]; then
  CERT_POLL_INTERVAL="$SSL_CERT_POLL_INTERVAL_SECONDS"
else
  CERT_POLL_INTERVAL="2"
fi

if [ "$1" = "--main" ] || [ "$1" = "--system" ]; then
  SLUG="main"
else
  SLUG="$(resolve_slug "$1")"
fi

if [ "$SLUG" = "main" ]; then
  COMPOSE_FILE="$SCRIPT_DIR/../docker-compose.yml"
  SERVICE="slim"
else
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

compose_letsencrypt_hosts() {
  if ! config_output=$(compose_cmd config 2>/dev/null); then
    return 1
  fi

  hosts=$(printf '%s\n' "$config_output" | awk 'tolower($1) ~ /letsencrypt_host:/ { $1=""; gsub("^[[:space:]]+", ""); print; exit }')
  hosts=$(normalize_csv_list "$hosts")

  if [ -n "$hosts" ]; then
    printf '%s' "$hosts"
    return 0
  fi

  return 1
}

resolve_letsencrypt_hosts() {
  if hosts=$(compose_letsencrypt_hosts 2>/dev/null); then
    if [ -n "$hosts" ]; then
      printf '%s' "$hosts"
      return 0
    fi
  fi

  if [ -n "${LETSENCRYPT_HOST+x}" ] && [ -n "$LETSENCRYPT_HOST" ]; then
    printf '%s' "$(normalize_csv_list "$LETSENCRYPT_HOST")"
    return 0
  fi

  if [ "$SLUG" = "main" ]; then
    base_host="${SLIM_LETSENCRYPT_HOST:-${SLIM_VIRTUAL_HOST:-${BASE_DOMAIN:-}}}"
    base_host=$(trim "$base_host")

    if [ -n "$base_host" ]; then
      marketing="$(normalize_csv_list "$MARKETING_DOMAINS")"
      if [ -n "$marketing" ]; then
        printf '%s,%s' "$base_host" "$marketing"
      else
        printf '%s' "$base_host"
      fi
      return 0
    fi
  elif [ -n "$BASE_DOMAIN" ]; then
    printf '%s.%s' "$SLUG" "$BASE_DOMAIN"
    return 0
  fi

  return 1
}

certificate_present_for_domain() {
  domain=$(trim "$1")

  if [ -z "$domain" ]; then
    return 1
  fi

  candidate_files=$(cat <<EOF
$CERT_DIR/${domain}.crt
$CERT_DIR/${domain}.fullchain.pem
$CERT_DIR/${domain}.pem
$CERT_DIR/${domain}/fullchain.pem
$CERT_DIR/${domain}/fullchain.cer
$CERT_DIR/${domain}/fullchain.crt
$CERT_DIR/${domain}/cert.pem
EOF
)

  for candidate in $candidate_files; do
    if [ -f "$candidate" ]; then
      return 0
    fi
  done

  return 1
}

acme_logs_show_success() {
  domain=$(trim "$1")
  since_ref="$2"

  if [ -z "$domain" ]; then
    return 1
  fi

  if ! command -v docker >/dev/null 2>&1; then
    return 1
  fi

  if ! docker ps --format '{{.Names}}' | grep -qx "$ACME_CONTAINER_NAME" >/dev/null 2>&1; then
    return 1
  fi

  logs=$(docker logs --since "$since_ref" "$ACME_CONTAINER_NAME" 2>/dev/null || true)
  if [ -z "$logs" ]; then
    return 1
  fi

  if printf '%s\n' "$logs" | grep -i "$domain" | grep -Ei "(success|succeed|valid|renew|issued|certificate)" >/dev/null 2>&1; then
    return 0
  fi

  return 1
}

WAIT_MISSING_CERT_DOMAINS=""

wait_for_certificate_confirmation() {
  domains=$(normalize_csv_list "$1")
  since_ref="$2"

  if [ -z "$domains" ]; then
    echo "Unable to determine target domains for certificate verification" >&2
    return 1
  fi

  start_ts=$(date +%s)

  while :; do
    missing_domains=""

    for domain in $(printf '%s' "$domains" | tr ',' ' '); do
      domain=$(trim "$domain")
      if [ -z "$domain" ]; then
        continue
      fi

      if certificate_present_for_domain "$domain"; then
        continue
      fi

      if acme_logs_show_success "$domain" "$since_ref"; then
        continue
      fi

      missing_domains="$missing_domains $domain"
    done

    if [ -z "$missing_domains" ]; then
      WAIT_MISSING_CERT_DOMAINS=""
      return 0
    fi

    now=$(date +%s)
    if [ $((now - start_ts)) -ge "$CERT_WAIT_SECONDS" ]; then
      WAIT_MISSING_CERT_DOMAINS=$(printf '%s' "$missing_domains" | sed 's/^ //;s/  */ /g')
      break
    fi

    sleep "$CERT_POLL_INTERVAL"
  done

  return 1
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

if [ "$RECREATE" = "1" ]; then
  ACTION="up -d --force-recreate"
else
  ACTION="restart"
fi

if [ "$SLUG" = "main" ]; then
  if ! $DOCKER_COMPOSE -f "$COMPOSE_FILE" $ACTION "$SERVICE" >/dev/null; then
    echo "Failed to restart main services" >&2
    exit 1
  fi
else
  if ! $DOCKER_COMPOSE -f "$COMPOSE_FILE" -p "$SLUG" $ACTION "$SERVICE" --no-deps >/dev/null; then
    echo "Failed to restart tenant application" >&2
    exit 1
  fi
fi

LOG_REFERENCE_TIME=$(date -Iseconds 2>/dev/null || date -u +"%Y-%m-%dT%H:%M:%SZ")

WEBHOOK_RESULT=0
if [ "$RELOAD_FLAG" = "0" ]; then
  echo "NGINX_RELOAD=0 set; skipping nginx reload" >&2
else
  reload_via_webhook
  WEBHOOK_RESULT=$?

  if [ "$WEBHOOK_RESULT" -eq 2 ]; then
    echo "nginx reload webhook disabled, falling back to docker exec" >&2
  elif [ "$WEBHOOK_RESULT" -ne 0 ]; then
    echo "Falling back to docker exec for nginx reload" >&2
  fi

  if [ "$WEBHOOK_RESULT" -ne 0 ]; then
    if ! reload_via_docker_exec; then
      echo "Failed to trigger nginx reload via webhook or docker exec" >&2
      exit 1
    fi
  fi
fi

TARGET_DOMAINS=$(resolve_letsencrypt_hosts || true)

if ! wait_for_certificate_confirmation "$TARGET_DOMAINS" "$LOG_REFERENCE_TIME"; then
  if [ -n "$WAIT_MISSING_CERT_DOMAINS" ]; then
    echo "Certificate files or success logs for '$WAIT_MISSING_CERT_DOMAINS' not found after $CERT_WAIT_SECONDS seconds" >&2
  elif [ -n "$TARGET_DOMAINS" ]; then
    echo "Certificate files or success logs for '$TARGET_DOMAINS' not found after $CERT_WAIT_SECONDS seconds" >&2
  else
    echo "Failed to validate certificate issuance for slug '$SLUG'" >&2
  fi
  exit 1
fi

printf '{"status":"renewed","slug":"%s"}\n' "$SLUG"
