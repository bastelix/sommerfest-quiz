#!/bin/sh
# Force renew SSL certificate for a tenant via acme-companion
set -e

if [ "$#" -lt 1 ]; then
  echo "Usage: $0 <tenant-slug>" >&2
  exit 1
fi

SLUG="$(echo "$1" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9-]/-/g')"
RELOADER_URL="${NGINX_RELOADER_URL:-http://nginx-reloader:8080/reload}"
RELOAD_TOKEN="${NGINX_RELOAD_TOKEN:-changeme}"

curl -fs -X POST -H "X-Token: $RELOAD_TOKEN" "$RELOADER_URL" >/dev/null

printf '{"status":"renewed","slug":"%s"}\n' "$SLUG"
