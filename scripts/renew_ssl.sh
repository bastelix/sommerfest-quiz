#!/bin/sh
# Force renew SSL certificate for a tenant via acme-companion
set -e

if [ "$#" -lt 1 ]; then
  echo "Usage: $0 <tenant-slug>" >&2
  exit 1
fi

SLUG="$(echo "$1" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9-]/-/g')"
ACME_CONTAINER="${ACME_CONTAINER:-acme-companion}"

if ! docker ps --format '{{.Names}}' | grep -q "^${ACME_CONTAINER}$"; then
  echo "{\"error\":\"acme container not running\"}" >&2
  exit 1
fi

docker exec "$ACME_CONTAINER" /app/force_renew >/dev/null
# trigger reload to activate renewed certs
docker exec "$ACME_CONTAINER" /app/signal_le_service >/dev/null

printf '{"status":"renewed","slug":"%s"}\n' "$SLUG"
