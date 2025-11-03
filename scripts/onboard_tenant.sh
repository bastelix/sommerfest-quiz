#!/bin/sh
# Onboard a new tenant with dedicated subdomain and SSL
set -e

log() {
  echo "$1"
}

error_exit() {
  echo "Fehler: $1" >&2
  exit 1
}

SCRIPT_DIR="$(dirname "$0")"
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
TENANTS_DIR="${TENANTS_DIR:-$SCRIPT_DIR/../tenants}"
TENANT_DIR="$TENANTS_DIR/$SLUG"
DATA_DIR="$TENANT_DIR/data"
COMPOSE_FILE="$TENANT_DIR/docker-compose.yml"
DOMAIN_SUFFIX="${MAIN_DOMAIN:-$DOMAIN}"

log "Starte Onboarding für Tenant '$SLUG'"

log "Prüfe Domain-Konfiguration"
if [ -z "$DOMAIN_SUFFIX" ]; then
  error_exit "MAIN_DOMAIN oder DOMAIN muss gesetzt sein"
fi

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
case "$(printf '%s' "$CLIENT_MAX_BODY_SIZE" | tr '[:upper:]' '[:lower:]')" in
  5m|5mb|5mib)
    BODY_LIMIT_MIDDLEWARE="quizrace-body-limit-5m@file"
    ;;
  10m|10mb|10mib)
    BODY_LIMIT_MIDDLEWARE="quizrace-body-limit-10m@file"
    ;;
  *)
    BODY_LIMIT_MIDDLEWARE="quizrace-body-limit-50m@file"
    ;;
esac

ROUTER_PREFIX=$(printf '%s' "$SLUG" | tr '[:upper:]' '[:lower:]' | tr -cs 'a-z0-9' '-')
SERVICE_NAME="${ROUTER_PREFIX:-tenant}-service"
ROUTER_WEB="${ROUTER_PREFIX:-tenant}-web"
ROUTER_SECURE="${ROUTER_PREFIX:-tenant}-secure"

cat > "$COMPOSE_FILE" <<YAML
version: '3.8'
services:
  app:
    image: ${IMAGE}
    container_name: ${SLUG}_app
    command: php -S 0.0.0.0:8080 -t public public/router.php
    expose:
      - "8080"
    volumes:
      - ./data:/var/www/data
    networks:
      - ${NETWORK}
    labels:
      - traefik.enable=true
      - traefik.docker.network=${NETWORK}
      - traefik.http.services.${SERVICE_NAME}.loadbalancer.server.port=8080
      - traefik.http.routers.${ROUTER_WEB}.entrypoints=web
      - traefik.http.routers.${ROUTER_WEB}.rule=Host(`$SLUG.${DOMAIN_SUFFIX}`)
      - traefik.http.routers.${ROUTER_WEB}.middlewares=quizrace-https-redirect@file
      - traefik.http.routers.${ROUTER_WEB}.service=${SERVICE_NAME}
      - traefik.http.routers.${ROUTER_SECURE}.entrypoints=websecure
      - traefik.http.routers.${ROUTER_SECURE}.rule=Host(`$SLUG.${DOMAIN_SUFFIX}`)
      - traefik.http.routers.${ROUTER_SECURE}.service=${SERVICE_NAME}
      - traefik.http.routers.${ROUTER_SECURE}.tls=true
      - traefik.http.routers.${ROUTER_SECURE}.tls.certresolver=letsencrypt
      - traefik.http.routers.${ROUTER_SECURE}.middlewares=quizrace-security-headers@file,${BODY_LIMIT_MIDDLEWARE}

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

echo "Tenant '$SLUG' deployed under https://${SLUG}.${DOMAIN_SUFFIX}"
echo "{\"status\": \"success\", \"slug\": \"${SLUG}\", \"url\": \"https://${SLUG}.${DOMAIN_SUFFIX}\"}"
