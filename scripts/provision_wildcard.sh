#!/bin/sh
# Provision a wildcard certificate via acme-companion/acme.sh
set -e

BASE_DIR="$(dirname "$0")/.."
ENV_FILE="$BASE_DIR/.env"

usage() {
  cat <<USAGE >&2
Usage: $0 [--force] [--domain <domain>]

Ensures a wildcard certificate for the given domain exists in certs/.
If no domain is provided, the script uses MAIN_DOMAIN or DOMAIN from .env.

Environment variables in .env:
  ACME_WILDCARD_PROVIDER   DNS plugin passed to acme.sh (e.g. dns_cf).
                           Leave empty or set to "manual" to handle the
                           DNS challenge manually.
  ACME_WILDCARD_SERVICE    Docker Compose service name (default: acme-companion)
  ACME_WILDCARD_SERVER     Optional ACME server URI
  ACME_WILDCARD_USE_STAGING  Set to 1 to request from the staging endpoint
  ACME_WILDCARD_ISSUE_FLAGS  Additional flags appended to the acme.sh --issue call
  ACME_WILDCARD_INSTALL_FLAGS Additional flags appended to the --install-cert call
  ACME_WILDCARD_ENV_*      Extra variables exported for the DNS plugin (e.g. ACME_WILDCARD_ENV_CF_Token)
USAGE
}

if [ ! -f "$ENV_FILE" ]; then
  echo "Environment file $ENV_FILE not found" >&2
  exit 1
fi

get_env_value() {
  key="$1"
  default_value="$2"
  raw_value=$(grep -E "^${key}=" "$ENV_FILE" | tail -n 1 | cut -d '=' -f2-)
  if [ -z "$raw_value" ]; then
    printf '%s' "$default_value"
    return
  fi

  value=$(printf '%s' "$raw_value" | sed 's/[[:space:]]*#.*$//' | tr -d '\r')
  value=$(printf '%s' "$value" | sed 's/^ *//;s/ *$//')
  value=$(printf '%s' "$value" | sed 's/^"//;s/"$//')
  value=$(printf '%s' "$value" | sed "s/^'//;s/'$//")
  if [ -z "$value" ]; then
    printf '%s' "$default_value"
  else
    printf '%s' "$value"
  fi
}

FORCE=0
TARGET_DOMAIN=""

while [ "$#" -gt 0 ]; do
  case "$1" in
    --force)
      FORCE=1
      ;;
    --domain)
      shift
      if [ "$#" -eq 0 ]; then
        echo "Missing value for --domain" >&2
        exit 1
      fi
      TARGET_DOMAIN="$1"
      ;;
    --domain=*)
      TARGET_DOMAIN=${1#*=}
      ;;
    --help|-h)
      usage
      exit 0
      ;;
    *)
      if [ -z "$TARGET_DOMAIN" ]; then
        TARGET_DOMAIN="$1"
      else
        echo "Unknown argument: $1" >&2
        usage
        exit 1
      fi
      ;;
  esac
  shift
done

if [ -z "$TARGET_DOMAIN" ]; then
  TARGET_DOMAIN="$(get_env_value 'MAIN_DOMAIN' '')"
fi

if [ -z "$TARGET_DOMAIN" ]; then
  TARGET_DOMAIN="$(get_env_value 'DOMAIN' '')"
fi

if [ -z "$TARGET_DOMAIN" ]; then
  echo "No domain provided and neither MAIN_DOMAIN nor DOMAIN set in .env" >&2
  exit 1
fi

CERT_PATH="$BASE_DIR/certs/$TARGET_DOMAIN.crt"
KEY_PATH="$BASE_DIR/certs/$TARGET_DOMAIN.key"

mkdir -p "$BASE_DIR/certs"

if [ -f "$CERT_PATH" ] && [ -f "$KEY_PATH" ] && [ "$FORCE" -eq 0 ]; then
  echo "Wildcard certificate already present at certs/$TARGET_DOMAIN.crt" >&2
  exit 0
fi

ACME_PROVIDER="$(get_env_value 'ACME_WILDCARD_PROVIDER' '')"
MANUAL_MODE=0
if [ -z "$ACME_PROVIDER" ] || [ "$ACME_PROVIDER" = "manual" ]; then
  MANUAL_MODE=1
  ACME_PROVIDER=""
fi

if [ "$MANUAL_MODE" -eq 0 ] && [ -z "$ACME_PROVIDER" ]; then
  echo "ACME_WILDCARD_PROVIDER must be set in .env (e.g. dns_cf)" >&2
  exit 1
fi

if [ "$MANUAL_MODE" -eq 1 ]; then
  echo "Running ACME manual DNS mode. You will need to create TXT records yourself." >&2
fi

ACME_SERVICE="$(get_env_value 'ACME_WILDCARD_SERVICE' 'acme-companion')"
ACME_SERVER="$(get_env_value 'ACME_WILDCARD_SERVER' '')"
ACME_USE_STAGING="$(get_env_value 'ACME_WILDCARD_USE_STAGING' '0')"
ACME_ISSUE_FLAGS="$(get_env_value 'ACME_WILDCARD_ISSUE_FLAGS' '')"
ACME_INSTALL_FLAGS="$(get_env_value 'ACME_WILDCARD_INSTALL_FLAGS' '')"
ACCOUNT_EMAIL="$(get_env_value 'ACME_WILDCARD_ACCOUNT_EMAIL' '')"
if [ -z "$ACCOUNT_EMAIL" ]; then
  ACCOUNT_EMAIL="$(get_env_value 'LETSENCRYPT_EMAIL' '')"
fi

if [ -z "$ACCOUNT_EMAIL" ]; then
  echo "Set LETSENCRYPT_EMAIL or ACME_WILDCARD_ACCOUNT_EMAIL in .env" >&2
  exit 1
fi

ACME_ENV_VARS=""

append_acme_env_var() {
  _var="$1"
  _value="$2"

  if [ -z "$_var" ]; then
    return
  fi

  # Avoid adding duplicate entries while still exporting the latest value.
  case " $ACME_ENV_VARS " in
    *" $_var "*)
      export "$_var=$_value"
      return
      ;;
  esac

  export "$_var=$_value"
  if [ -z "$ACME_ENV_VARS" ]; then
    ACME_ENV_VARS="$_var"
  else
    ACME_ENV_VARS="$ACME_ENV_VARS $_var"
  fi
}
while IFS= read -r line; do
  case "$line" in
    ACME_WILDCARD_ENV_*=*)
      key=${line%%=*}
      var=${key#ACME_WILDCARD_ENV_}
      if [ -z "$var" ]; then
        continue
      fi
      raw=${line#*=}
      value=$(printf '%s' "$raw" | sed 's/[[:space:]]*#.*$//' | tr -d '\r')
      value=$(printf '%s' "$value" | sed 's/^ *//;s/ *$//')
      value=$(printf '%s' "$value" | sed 's/^"//;s/"$//')
      value=$(printf '%s' "$value" | sed "s/^'//;s/'$//")
      if [ -z "$value" ]; then
        continue
      fi
      append_acme_env_var "$var" "$value"

      # Some acme.sh DNS plugins use camel-cased environment variables.
      # Accept the commonly used upper-case variants to reduce
      # configuration pitfalls when values come from infrastructure
      # secrets managers.
      if [ "$ACME_PROVIDER" = "dns_hetzner" ]; then
        case "$var" in
          HETZNER_TOKEN|HETZNER_API_TOKEN)
            append_acme_env_var "HETZNER_Token" "$value"
            ;;
        esac
      fi
      ;;
  esac
done < "$ENV_FILE"

if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  DOCKER_COMPOSE="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
  DOCKER_COMPOSE="docker-compose"
else
  echo "docker compose or docker-compose is required" >&2
  exit 1
fi

COMPOSE_FILE="$BASE_DIR/docker-compose.yml"
if [ ! -f "$COMPOSE_FILE" ]; then
  echo "docker-compose.yml not found in project root" >&2
  exit 1
fi

if [ "$DOCKER_COMPOSE" = "docker compose" ]; then
  SERVICE_CHECK=$($DOCKER_COMPOSE -f "$COMPOSE_FILE" ps -q "$ACME_SERVICE" 2>/dev/null || true)
else
  SERVICE_CHECK=$($DOCKER_COMPOSE -f "$COMPOSE_FILE" ps -q "$ACME_SERVICE" 2>/dev/null || true)
fi

if [ -z "$SERVICE_CHECK" ]; then
  $DOCKER_COMPOSE -f "$COMPOSE_FILE" up -d "$ACME_SERVICE" >/dev/null
fi

set -- $DOCKER_COMPOSE -f "$COMPOSE_FILE" run --rm
for var in $ACME_ENV_VARS; do
  set -- "$@" -e "$var"
done
set -- "$@" --entrypoint /app/acme.sh "$ACME_SERVICE" --home /app --config-home /etc/acme.sh/default --issue
if [ "$MANUAL_MODE" -eq 1 ]; then
  set -- "$@" --dns --yes-I-know-dns-manual-mode-enough-go-ahead-please
else
  set -- "$@" --dns "$ACME_PROVIDER"
fi
set -- "$@" -d "$TARGET_DOMAIN" -d "*.$TARGET_DOMAIN" --accountemail "$ACCOUNT_EMAIL"
if [ -n "$ACME_SERVER" ]; then
  set -- "$@" --server "$ACME_SERVER"
fi
if [ "$ACME_USE_STAGING" = "1" ]; then
  set -- "$@" --staging
fi
if [ -n "$ACME_ISSUE_FLAGS" ]; then
  for flag in $ACME_ISSUE_FLAGS; do
    set -- "$@" "$flag"
  done
fi
if [ "$MANUAL_MODE" -eq 0 ]; then
  "$@"
  exit 0
fi

TMP_OUTPUT=$(mktemp)
cleanup_tmp() {
  rm -f "$TMP_OUTPUT"
}
trap cleanup_tmp EXIT HUP INT TERM

set +e
"$@" >"$TMP_OUTPUT" 2>&1
STATUS=$?
set -e
cat "$TMP_OUTPUT"

if [ "$STATUS" -eq 0 ]; then
  exit 0
fi

TXT_DOMAIN_LINE=$(grep -E "Domain:" "$TMP_OUTPUT" | tail -n 1)
TXT_VALUE_LINE=$(grep -E "TXT[[:space:]]+(value|record)" "$TMP_OUTPUT" | tail -n 1)

TXT_DOMAIN=""
if [ -n "$TXT_DOMAIN_LINE" ]; then
  TXT_DOMAIN=$(printf '%s\n' "$TXT_DOMAIN_LINE" | awk "match(\$0, /'[^']*'/){print substr(\$0, RSTART+1, RLENGTH-2)}")
  if [ -z "$TXT_DOMAIN" ]; then
    TXT_DOMAIN=$(printf '%s\n' "$TXT_DOMAIN_LINE" | sed -e "s/.*Domain:[[:space:]]*//" -e "s/[\"']//g" -e 's/[[:space:]]*$//')
  fi
fi

TXT_VALUE=""
if [ -n "$TXT_VALUE_LINE" ]; then
  TXT_VALUE=$(printf '%s\n' "$TXT_VALUE_LINE" | awk "match(\$0, /'[^']*'/){print substr(\$0, RSTART+1, RLENGTH-2)}")
  if [ -z "$TXT_VALUE" ]; then
    TXT_VALUE=$(printf '%s\n' "$TXT_VALUE_LINE" | sed -e "s/.*TXT[[:space:]]*\([A-Za-z]*\):[[:space:]]*//" -e "s/[\"']//g" -e 's/[[:space:]]*$//')
  fi
fi

if [ -z "$TXT_VALUE" ]; then
  echo "Manual DNS challenge failed and the TXT value could not be determined. Review the output above." >&2
  exit "$STATUS"
fi

echo
echo "Manual DNS verification required."
if [ -n "$TXT_DOMAIN" ]; then
  echo "Create a TXT record for: $TXT_DOMAIN"
else
  echo "Create a TXT record for the _acme-challenge subdomain."
fi
echo "TXT value: $TXT_VALUE"
echo
printf "Press Enter after the TXT record has been created and propagated to continue..."
read -r _

cleanup_tmp
trap - EXIT HUP INT TERM

"$@"

set -- $DOCKER_COMPOSE -f "$COMPOSE_FILE" run --rm
for var in $ACME_ENV_VARS; do
  set -- "$@" -e "$var"
done
set -- "$@" --entrypoint /app/acme.sh "$ACME_SERVICE" --home /app --config-home /etc/acme.sh/default --install-cert -d "$TARGET_DOMAIN" --fullchain-file "/etc/nginx/certs/$TARGET_DOMAIN.crt" --key-file "/etc/nginx/certs/$TARGET_DOMAIN.key" --reloadcmd ""
if [ -n "$ACME_INSTALL_FLAGS" ]; then
  for flag in $ACME_INSTALL_FLAGS; do
    set -- "$@" "$flag"
  done
fi
"$@"

if [ ! -f "$CERT_PATH" ] || [ ! -f "$KEY_PATH" ]; then
  echo "Wildcard certificate issuance succeeded but files are missing in certs/" >&2
  exit 1
fi

chmod 640 "$CERT_PATH" 2>/dev/null || true
chmod 600 "$KEY_PATH" 2>/dev/null || true

echo "Wildcard certificate ready at certs/$TARGET_DOMAIN.crt"

