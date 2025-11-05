#!/bin/sh
# Onboard a new tenant with dedicated subdomain and SSL. Das Skript rollt fehlgeschlagene
# Deployments automatisch zurück und entfernt angelegte Ressourcen.
set -e

log() {
  echo "$1"
}

error_exit() {
  echo "Fehler: $1" >&2
  exit 1
}

SCRIPT_DIR="$(dirname "$0")"
BASE_DIR="$SCRIPT_DIR/.."
if [ -f "$SCRIPT_DIR/../.env" ]; then
  set -a
  # shellcheck source=/dev/null
  . "$SCRIPT_DIR/../.env"
  set +a
fi

if [ "$#" -lt 1 ]; then
  error_exit "Usage: $0 <tenant-slug>"
fi

SLUG=$(echo "$1" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9-]/-/g')
OFFBOARD_SLUG="$SLUG"
TENANTS_DIR="${TENANTS_DIR:-$SCRIPT_DIR/../tenants}"
TENANT_DIR="$TENANTS_DIR/$SLUG"
DATA_DIR="$TENANT_DIR/data"
COMPOSE_FILE="$TENANT_DIR/docker-compose.yml"
DOMAIN_SUFFIX="${MAIN_DOMAIN:-$DOMAIN}"
EMAIL="${LETSENCRYPT_EMAIL:-admin@quizrace.app}"

OFFBOARD_SCRIPT="$SCRIPT_DIR/offboard_tenant.sh"
VHOST_FILE=""

cleanup() {
  exit_code="$1"
  trap - EXIT

  if [ "$exit_code" -ne 0 ]; then
    if [ -n "$OFFBOARD_SCRIPT" ] && [ -x "$OFFBOARD_SCRIPT" ]; then
      if ! "$OFFBOARD_SCRIPT" "$OFFBOARD_SLUG" >/dev/null 2>&1; then
        if [ -n "$TENANT_DIR" ] && [ -d "$TENANT_DIR" ]; then
          rm -rf "$TENANT_DIR"
        fi
      fi
    elif [ -n "$TENANT_DIR" ] && [ -d "$TENANT_DIR" ]; then
      rm -rf "$TENANT_DIR"
    fi

    if [ -n "$VHOST_FILE" ] && [ -f "$VHOST_FILE" ]; then
      rm -f "$VHOST_FILE"
    fi
  fi

  exit "$exit_code"
}

trap 'cleanup "$?"' EXIT

log "Starte Onboarding für Tenant '$SLUG'"

log "Prüfe Domain-Konfiguration"
if [ -z "$DOMAIN_SUFFIX" ]; then
  error_exit "MAIN_DOMAIN oder DOMAIN muss gesetzt sein"
fi


VHOST_FILE="$BASE_DIR/vhost.d/${SLUG}.${DOMAIN_SUFFIX}"

log "Prüfe APP_IMAGE"
if [ -z "$APP_IMAGE" ]; then
  error_exit "APP_IMAGE ist nicht gesetzt"
fi

IMAGE="$APP_IMAGE"
# Docker network for reverse proxy
NETWORK="${NETWORK:-webproxy}"

# minimal free space in MB required to create a tenant
MIN_DISK_MB=100

log "Ermittle docker-compose Befehl"
# detect docker compose command
if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  DOCKER_COMPOSE="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
  DOCKER_COMPOSE="docker-compose"
else
  error_exit "docker compose oder docker-compose ist nicht verfügbar"
fi

log "Validiere Domain ${SLUG}.${DOMAIN_SUFFIX}"
# validate generated domain
if ! echo "${SLUG}.${DOMAIN_SUFFIX}" | grep -Eq '^[a-z0-9-]+(\.[a-z0-9-]+)+$'; then
  error_exit "Generierte Domain '${SLUG}.${DOMAIN_SUFFIX}' ist ungültig."
fi

log "Prüfe Docker-Netzwerk ${NETWORK}"
# ensure required docker network exists
if ! docker network inspect ${NETWORK} >/dev/null 2>&1; then
  error_exit "Netzwerk '${NETWORK}' existiert nicht. Bitte zuerst mit 'docker network create --driver bridge ${NETWORK}' anlegen."
fi

log "Prüfe Containerkollisionen"
# avoid container name collisions
if docker ps -a --format '{{.Names}}' | grep -q "^${SLUG}_app$"; then
  error_exit "Ein Container mit dem Namen '${SLUG}_app' existiert bereits."
fi

log "Prüfe verfügbares Docker-Image ${IMAGE}"
# optional image check
if ! docker image inspect ${IMAGE} >/dev/null 2>&1; then
  echo "Warnung: Docker-Image '${IMAGE}' ist lokal nicht vorhanden. Der Container wird versuchen, es herunterzuladen."
fi

log "Prüfe freien Speicher"
# check for sufficient disk space
AVAILABLE_MB=$(df -Pm "$(dirname "$TENANT_DIR")" | awk 'NR==2{print $4}')
if [ "$AVAILABLE_MB" -lt "$MIN_DISK_MB" ]; then
  error_exit "Zu wenig Speicherplatz (nur ${AVAILABLE_MB}MB verfügbar)."
fi

log "Erstelle Tenant- und Datenverzeichnis"
if [ -d "$TENANT_DIR" ]; then
  error_exit "Tenant directory '$TENANT_DIR' already exists"
fi

if ! mkdir -p "$DATA_DIR"; then
  error_exit "Konnte Verzeichnis '$DATA_DIR' nicht anlegen"
fi

log "Erstelle docker-compose Datei"
cat > "$COMPOSE_FILE" <<YAML
version: '3.8'
services:
  app:
    image: ${IMAGE}
    container_name: ${SLUG}_app
    environment:
      - VIRTUAL_HOST=${SLUG}.${DOMAIN_SUFFIX}
      - LETSENCRYPT_HOST=${SLUG}.${DOMAIN_SUFFIX}
      - LETSENCRYPT_EMAIL=${EMAIL}
      - VIRTUAL_PORT=8080
    command: php -S 0.0.0.0:8080 -t public public/router.php
    expose:
      - "8080"
    volumes:
      - ./data:/var/www/data
    networks:
      - ${NETWORK}
    labels:
      - "com.github.nginxproxy.acme-companion.nginx_proxy=true"

networks:
  ${NETWORK}:
    external: true
YAML

log "Validiere docker-compose Syntax"
# validate compose file syntax
if ! $DOCKER_COMPOSE -f "$COMPOSE_FILE" config >/dev/null 2>&1; then
  error_exit "docker compose config fehlgeschlagen (Syntaxfehler?)"
fi

log "Starte Tenant-Stack"
# start the tenant stack
if ! compose_out=$($DOCKER_COMPOSE -f "$COMPOSE_FILE" -p "$SLUG" up -d 2>&1); then
  echo "$compose_out"
  error_exit "docker compose konnte den Tenant-Container nicht starten."
fi

log "Löse Nginx-Reload aus"
# optional reload to speed up certificate issuance
# Without a successful reload no certificate will be requested, so abort on failure
RELOADER_URL="${NGINX_RELOADER_URL:-http://nginx-reloader:8080/reload}"
RELOAD_TOKEN="${NGINX_RELOAD_TOKEN:-changeme}"
if ! curl -fs -X POST -H "X-Token: $RELOAD_TOKEN" "$RELOADER_URL" >/dev/null; then
  error_exit "Konnte Nginx-Reload nicht auslösen; kein Zertifikat beantragt"
fi

log "Starte Container neu"
# restart the tenant container to pick up the certificate
if ! $DOCKER_COMPOSE -f "$COMPOSE_FILE" -p "$SLUG" restart >/dev/null; then
  error_exit "Konnte Tenant-Container nicht neu starten"
fi

log "Löse zweiten Nginx-Reload aus"
# trigger a second reload so nginx picks up the certificate
if ! curl -fs -X POST -H "X-Token: $RELOAD_TOKEN" "$RELOADER_URL" >/dev/null; then
  echo "Warnung: Konnte zweiten Nginx-Reload nicht auslösen" >&2
fi

echo "Tenant '$SLUG' deployed under https://${SLUG}.${DOMAIN_SUFFIX}"
echo "{\"status\": \"success\", \"slug\": \"${SLUG}\", \"url\": \"https://${SLUG}.${DOMAIN_SUFFIX}\"}"
