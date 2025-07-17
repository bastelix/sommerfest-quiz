#!/bin/sh

TEMPLATE_PATH="/etc/docker-gen/templates/nginx.tmpl"

if [ ! -f "$TEMPLATE_PATH" ]; then
  echo "[entrypoint] nginx.tmpl fehlt – Standard wird kopiert..."
  mkdir -p "$(dirname "$TEMPLATE_PATH")"
  cp /defaults/nginx.tmpl "$TEMPLATE_PATH"
fi

exec /usr/local/bin/docker-gen "$@"
