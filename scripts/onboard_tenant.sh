#!/bin/sh
# Onboard a new tenant with dedicated subdomain and SSL
set -e

if [ "$#" -lt 1 ]; then
  echo "Usage: $0 <tenant-slug>" >&2
  exit 1
fi

SLUG=$(echo "$1" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9-]/-/g')
TENANT_DIR="$(dirname "$0")/../tenants/$SLUG"
COMPOSE_FILE="$TENANT_DIR/docker-compose.yml"
DOMAIN_SUFFIX="quizrace.app"
EMAIL="${LETSENCRYPT_EMAIL:-admin@quizrace.app}"
IMAGE="${APP_IMAGE:-your-app-image}"
NETWORK="webproxy"

if [ -d "$TENANT_DIR" ]; then
  echo "Tenant directory '$TENANT_DIR' already exists" >&2
  exit 1
fi

mkdir -p "$TENANT_DIR"

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

# start the tenant stack
docker compose -f "$COMPOSE_FILE" -p "$SLUG" up -d

# optional reload to speed up certificate issuance
if docker ps --format '{{.Names}}' | grep -q '^nginx$'; then
  docker exec nginx nginx -s reload || true
fi
if docker ps --format '{{.Names}}' | grep -q '^acme-companion$'; then
  docker exec acme-companion /app/signal_le_service || true
fi

echo "Tenant '$SLUG' deployed under https://${SLUG}.${DOMAIN_SUFFIX}"
