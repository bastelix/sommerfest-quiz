#!/bin/sh
# Onboard a new tenant with dedicated subdomain and SSL
set -e

error_exit() {
  echo "Fehler: $1" >&2
  exit 1
}

if [ "$#" -lt 1 ]; then
  error_exit "Usage: $0 <tenant-slug>"
fi

SLUG=$(echo "$1" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9-]/-/g')
TENANT_DIR="$(dirname "$0")/../tenants/$SLUG"
COMPOSE_FILE="$TENANT_DIR/docker-compose.yml"
DOMAIN_SUFFIX="${MAIN_DOMAIN:-$DOMAIN}"
EMAIL="${LETSENCRYPT_EMAIL:-admin@quizrace.app}"

if [ -z "$DOMAIN_SUFFIX" ]; then
  error_exit "MAIN_DOMAIN oder DOMAIN muss gesetzt sein"
fi

if [ -z "$APP_IMAGE" ]; then
  error_exit "APP_IMAGE ist nicht gesetzt"
fi

IMAGE="$APP_IMAGE"
# Docker network for reverse proxy
NETWORK="${NETWORK:-webproxy}"

# minimal free space in MB required to create a tenant
MIN_DISK_MB=100

# validate generated domain
if ! echo "${SLUG}.${DOMAIN_SUFFIX}" | grep -Eq '^[a-z0-9-]+(\.[a-z0-9-]+)+$'; then
  error_exit "Generierte Domain '${SLUG}.${DOMAIN_SUFFIX}' ist ungültig."
fi

# ensure required docker network exists
if ! docker network inspect ${NETWORK} >/dev/null 2>&1; then
  error_exit "Netzwerk '${NETWORK}' existiert nicht. Bitte zuerst mit 'docker network create --driver bridge ${NETWORK}' anlegen."
fi

# avoid container name collisions
if docker ps -a --format '{{.Names}}' | grep -q "^${SLUG}_app$"; then
  error_exit "Ein Container mit dem Namen '${SLUG}_app' existiert bereits."
fi

# optional image check
if ! docker image inspect ${IMAGE} >/dev/null 2>&1; then
  echo "Warnung: Docker-Image '${IMAGE}' ist lokal nicht vorhanden. Der Container wird versuchen, es herunterzuladen."
fi

# check for sufficient disk space
AVAILABLE_MB=$(df -Pm "$(dirname "$TENANT_DIR")" | awk 'NR==2{print $4}')
if [ "$AVAILABLE_MB" -lt "$MIN_DISK_MB" ]; then
  error_exit "Zu wenig Speicherplatz (nur ${AVAILABLE_MB}MB verfügbar)."
fi

if [ -d "$TENANT_DIR" ]; then
  error_exit "Tenant directory '$TENANT_DIR' already exists"
fi

if ! mkdir -p "$TENANT_DIR"; then
  error_exit "Konnte Verzeichnis '$TENANT_DIR' nicht anlegen"
fi

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
    networks:
      - ${NETWORK}
    labels:
      - "com.github.jrcs.letsencrypt_nginx_proxy_companion.nginx_proxy=true"

networks:
  ${NETWORK}:
    external: true
YAML

# validate compose file syntax
if ! docker compose -f "$COMPOSE_FILE" config >/dev/null 2>&1; then
  error_exit "docker compose config fehlgeschlagen (Syntaxfehler?)"
fi

# start the tenant stack
if ! compose_out=$(docker compose -f "$COMPOSE_FILE" -p "$SLUG" up -d 2>&1); then
  echo "$compose_out"
  error_exit "docker compose konnte den Tenant-Container nicht starten."
fi

# optional reload to speed up certificate issuance
RELOADER_URL="${NGINX_RELOADER_URL:-http://nginx-reloader:8080/reload}"
RELOAD_TOKEN="${NGINX_RELOAD_TOKEN:-changeme}"
curl -fs -X POST -H "X-Token: $RELOAD_TOKEN" "$RELOADER_URL" >/dev/null || true

echo "Tenant '$SLUG' deployed under https://${SLUG}.${DOMAIN_SUFFIX}"
echo "{\"status\": \"success\", \"slug\": \"${SLUG}\", \"url\": \"https://${SLUG}.${DOMAIN_SUFFIX}\"}"
