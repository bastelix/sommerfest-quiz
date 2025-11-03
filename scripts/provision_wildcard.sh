#!/bin/sh
# Provision a wildcard certificate through Traefik's ACME resolver
set -e

BASE_DIR="$(dirname "$0")/.."
ENV_FILE="$BASE_DIR/.env"
TRAEFIK_CONFIG="$BASE_DIR/config/traefik/traefik.yml"
ACME_STORAGE="$BASE_DIR/letsencrypt/acme.json"

usage() {
  cat <<USAGE >&2
Usage: $0 [--force] [--domain <domain>]

Updates the Traefik ACME resolver configuration and asks Traefik to
provision (or renew) a wildcard certificate for the given domain.
When no domain is specified the script falls back to MAIN_DOMAIN and
finally DOMAIN from .env.

Relevant .env settings:
  TRAEFIK_ACME_DNS_PROVIDER      Name of the Traefik/lego DNS provider
  TRAEFIK_ACME_DNS_RESOLVERS     Optional comma separated custom resolvers
  TRAEFIK_ACME_DNS_DELAY         Optional propagation delay (seconds)
  TRAEFIK_ACME_API_ENDPOINT      Base URL of the Traefik API (default http://traefik:8080)
  TRAEFIK_API_BASICAUTH          Optional "user:password" for Traefik API basic auth
  ACME_WILDCARD_PROVIDER         Legacy setting automatically mapped to
                                 TRAEFIK_ACME_DNS_PROVIDER (dns_ prefix is removed)
  ACME_WILDCARD_USE_STAGING      Set to 1 to keep using the Let's Encrypt
                                 staging server (mapped automatically)
USAGE
}

if [ ! -f "$ENV_FILE" ]; then
  echo "Environment file $ENV_FILE not found" >&2
  exit 1
fi

if [ ! -f "$TRAEFIK_CONFIG" ]; then
  echo "Traefik configuration $TRAEFIK_CONFIG not found" >&2
  exit 1
fi

log() {
  printf '%s\n' "$1" >&2
}

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

normalize_provider() {
  provider="$1"
  case "$provider" in
    dns_*)
      provider=${provider#dns_}
      ;;
  esac

  printf '%s' "$provider"
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

STAGING="$(get_env_value 'TRAEFIK_ACME_USE_STAGING' '')"
if [ -z "$STAGING" ]; then
  STAGING="$(get_env_value 'ACME_WILDCARD_USE_STAGING' '0')"
fi

provider="$(get_env_value 'TRAEFIK_ACME_DNS_PROVIDER' '')"
if [ -z "$provider" ]; then
  provider="$(get_env_value 'ACME_WILDCARD_PROVIDER' '')"
fi
provider="$(normalize_provider "$provider")"

if [ -z "$provider" ]; then
  log "TRAEFIK_ACME_DNS_PROVIDER (or legacy ACME_WILDCARD_PROVIDER) must be set in .env"
  exit 1
fi

dns_resolvers="$(get_env_value 'TRAEFIK_ACME_DNS_RESOLVERS' '')"
dns_delay="$(get_env_value 'TRAEFIK_ACME_DNS_DELAY' '0')"

DEFAULT_TRAEFIK_API_ENDPOINT='http://traefik:8080'
api_endpoint="$(get_env_value 'TRAEFIK_ACME_API_ENDPOINT' "$DEFAULT_TRAEFIK_API_ENDPOINT")"
api_basicauth="$(get_env_value 'TRAEFIK_API_BASICAUTH' '')"

extract_host() {
  python3 - "$1" <<'PY'
from urllib.parse import urlparse
import sys

raw = sys.argv[1]
parsed = urlparse(raw)
host = parsed.hostname or ""
print(host)
PY
}

host_resolves() {
  python3 - "$1" <<'PY'
import socket
import sys

host = sys.argv[1]
if not host:
    raise SystemExit(1)

try:
    socket.getaddrinfo(host, None)
except socket.gaierror:
    raise SystemExit(1)
else:
    raise SystemExit(0)
PY
}

endpoint_host="$(extract_host "$api_endpoint")"

if [ -z "$endpoint_host" ]; then
  log "Unable to determine host from Traefik API endpoint $api_endpoint"
elif [ "$endpoint_host" = "traefik" ] && ! host_resolves "$endpoint_host"; then
  fallback_endpoint='http://127.0.0.1:8080'
  log "Traefik host '$endpoint_host' not resolvable from here, falling back to $fallback_endpoint"
  api_endpoint="$fallback_endpoint"
elif [ "$api_endpoint" = "$DEFAULT_TRAEFIK_API_ENDPOINT" ] && ! host_resolves "$endpoint_host"; then
  fallback_endpoint='http://127.0.0.1:8080'
  log "Traefik API endpoint $api_endpoint unreachable, falling back to $fallback_endpoint"
  api_endpoint="$fallback_endpoint"
fi

if [ "$STAGING" = "1" ]; then
  acme_server="https://acme-staging-v02.api.letsencrypt.org/directory"
else
  acme_server=""
fi

update_traefik_config() {
  python3 - "$TRAEFIK_CONFIG" "$provider" "$dns_resolvers" "$dns_delay" "$TARGET_DOMAIN" "$acme_server" <<'PY'
from pathlib import Path
import sys

config_path = Path(sys.argv[1])
provider = sys.argv[2]
resolvers_raw = sys.argv[3]
delay = sys.argv[4]
_ = sys.argv[5]
acme_server = sys.argv[6]

text = config_path.read_text()
start_marker = "      # BEGIN TRAEFIK WILDCARD MANAGED BLOCK"
end_marker = "      # END TRAEFIK WILDCARD MANAGED BLOCK"

resolver_lines = []
resolvers = [value.strip() for value in resolvers_raw.replace("\n", ",").split(",") if value.strip()]
if resolvers:
    resolver_lines.append("        resolvers:")
    for resolver in resolvers:
        resolver_lines.append(f"          - \"{resolver}\"")

delay_line = []
if delay and delay != "0":
    delay_line = [f"        delayBeforeCheck: {delay}"]

server_line = []
if acme_server:
    server_line = [f"      caServer: \"{acme_server}\""]

managed = [
    start_marker,
    "      dnsChallenge:",
    f"        provider: \"{provider}\"",
]
managed.extend(delay_line)
managed.extend(resolver_lines)
managed.extend(server_line)
managed.append(end_marker)

replacement = "\n".join(managed) + "\n"

if start_marker in text and end_marker in text:
    import re
    pattern = re.compile(r"^\s*# BEGIN TRAEFIK WILDCARD MANAGED BLOCK.*?# END TRAEFIK WILDCARD MANAGED BLOCK\n", re.DOTALL | re.MULTILINE)
    text, count = pattern.subn(replacement, text)
    if count == 0:
        raise SystemExit("Failed to update managed block in traefik.yml")
else:
    anchor = "      storage: /letsencrypt/acme.json\n"
    if anchor not in text:
        raise SystemExit("Unable to locate certificatesResolvers section in traefik.yml")
    text = text.replace(anchor, anchor + replacement)

config_path.write_text(text)
PY
}

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
    if [ -n "$api_basicauth" ]; then
      curl -fsS -u "$api_basicauth" "$api_endpoint$path"
    else
      curl -fsS "$api_endpoint$path"
    fi
  else
    if [ -n "$api_basicauth" ]; then
      curl -fsS -u "$api_basicauth" -X "$method" "$api_endpoint$path" -H 'Content-Type: application/json' -d "$body" 2>/dev/null
    else
      curl -fsS -X "$method" "$api_endpoint$path" -H 'Content-Type: application/json' -d "$body" 2>/dev/null
    fi
  fi
}

read_certificate_status() {
  python3 - "$TARGET_DOMAIN" <<'PY'
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
    if main == domain or target_san in sans:
        not_after = cert.get("notAfter") or cert.get("NotAfter") or cert.get("expiry") or ""
        resolver = cert.get("issuer") or cert.get("resolver") or ""
        print(f"FOUND\t{not_after}\t{resolver}")
        break
else:
    print("MISSING\t\t")
PY
}

certificate_ready() {
  set +e
  response=$(traefik_api_request GET /api/tls/certificates)
  status=$?
  set -e
  if [ $status -ne 0 ] || [ -z "$response" ]; then
    echo "ERROR"
    return
  fi

  status_line=$(printf '%s' "$response" | read_certificate_status)
  printf '%s' "$status_line"
}

ensure_docker_compose() {
  if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
    echo "docker compose"
    return
  fi

  if command -v docker-compose >/dev/null 2>&1; then
    echo "docker-compose"
    return
  fi

  echo ""
}

DOCKER_COMPOSE=$(ensure_docker_compose)
if [ -z "$DOCKER_COMPOSE" ]; then
  echo "docker compose or docker-compose is required" >&2
  exit 1
fi

COMPOSE_FILE="$BASE_DIR/docker-compose.yml"
if [ ! -f "$COMPOSE_FILE" ]; then
  echo "docker-compose.yml not found in project root" >&2
  exit 1
fi

ensure_acme_storage() {
  if [ -d "$ACME_STORAGE" ]; then
    log "ACME storage path $ACME_STORAGE is a directory (likely created by Docker). Remove it so the file can be recreated."
    exit 1
  fi

  if [ ! -e "$ACME_STORAGE" ]; then
    mkdir -p "$(dirname "$ACME_STORAGE")"
    (
      umask 177
      touch "$ACME_STORAGE"
    )
  fi

  chmod 600 "$ACME_STORAGE"
  log "ACME storage file $ACME_STORAGE ready for Traefik (mode 600)"
}

ensure_acme_storage

update_traefik_config

if ! $DOCKER_COMPOSE -f "$COMPOSE_FILE" ps -q traefik >/dev/null 2>&1 || [ -z "$($DOCKER_COMPOSE -f "$COMPOSE_FILE" ps -q traefik 2>/dev/null)" ]; then
  log "Starting Traefik container"
  $DOCKER_COMPOSE -f "$COMPOSE_FILE" up -d traefik >/dev/null
fi

current_status="$(certificate_ready)"
status_type=$(printf '%s' "$current_status" | cut -f1)
expiry=$(printf '%s' "$current_status" | cut -f2)

if [ "$status_type" = "FOUND" ] && [ "$FORCE" -eq 0 ]; then
  if [ -n "$expiry" ]; then
    log "Wildcard certificate for *.$TARGET_DOMAIN already present (expires $expiry)"
  else
    log "Wildcard certificate for *.$TARGET_DOMAIN already present"
  fi
  exit 0
fi

log "Requesting wildcard certificate for $TARGET_DOMAIN via Traefik resolver ($provider)"

set +e
traefik_api_request POST /api/providers/reload ""
if [ $? -ne 0 ]; then
  log "Warning: unable to trigger Traefik provider reload via API"
fi
set -e

attempt=0
max_attempts=12
while [ $attempt -lt $max_attempts ]; do
  sleep 5
  attempt=$((attempt + 1))
  status_line="$(certificate_ready)"
  status_type=$(printf '%s' "$status_line" | cut -f1)
  expiry=$(printf '%s' "$status_line" | cut -f2)
  if [ "$status_type" = "FOUND" ]; then
    log "Wildcard certificate for *.$TARGET_DOMAIN ready (expires $expiry)"
    printf '{"status":"issued","domain":"%s","expires":"%s"}\n' "$TARGET_DOMAIN" "$expiry"
    exit 0
  fi
  if [ "$status_type" = "ERROR" ]; then
    log "Waiting for Traefik API to expose certificate list..."
  fi
done

log "Timed out waiting for Traefik to report the wildcard certificate"
printf '{"status":"timeout","domain":"%s"}\n' "$TARGET_DOMAIN"
exit 1
