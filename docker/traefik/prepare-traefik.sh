#!/bin/sh
set -eu

ACME_STORAGE="/letsencrypt/acme.json"
MARKETING_DIR="/etc/traefik/dynamic/marketing"

# Traefik requires the ACME storage file to exist and be writable by the
# Traefik process. When the file is mounted from the host, Docker creates it if
# necessary with default permissions, so we explicitly normalise them here.
if [ ! -f "$ACME_STORAGE" ]; then
    touch "$ACME_STORAGE"
fi
chmod 600 "$ACME_STORAGE"

ACTUAL_MODE=$(stat -c '%a' "$ACME_STORAGE")
if [ "$ACTUAL_MODE" != "600" ]; then
    echo "Fehler: $ACME_STORAGE muss die Berechtigung 600 besitzen, aktuell ist $ACTUAL_MODE gesetzt. Bitte korrigiere die Rechte auf dem Host." >&2
    exit 1
fi

# When the marketing configuration directory is mounted read-only, the helper
# above ensures it exists inside the image. The bind mount can still fail if the
# directory disappears, so we recreate it just in case.
if [ ! -d "$MARKETING_DIR" ]; then
    mkdir -p "$MARKETING_DIR"
fi
