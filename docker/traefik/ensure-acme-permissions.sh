#!/bin/sh
set -eu

ACME_STORAGE="/letsencrypt/acme.json"

if [ ! -e "$ACME_STORAGE" ]; then
    # The default Traefik entrypoint will take care of creating the file if it
    # is missing, but we ensure it exists so the permission fix below can run
    # without errors when bind mounting from the host.
    touch "$ACME_STORAGE" 2>/dev/null || true
fi

# Traefik refuses to use ACME storage files that have group or world access.
# When the file lives on a host filesystem that ignores chmod calls (e.g. some
# network shares on macOS/Windows), the operation may fail. We attempt to
# normalise the permissions and keep Traefik's startup scripts in control over
# how to proceed.
if ! chmod 600 "$ACME_STORAGE" 2>/dev/null; then
    echo "Warnung: Konnte die Berechtigungen von $ACME_STORAGE nicht auf 600 setzen." >&2
fi

exec /entrypoint.sh "$@"
