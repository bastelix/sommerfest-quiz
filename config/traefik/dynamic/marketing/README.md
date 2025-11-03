# Marketing dynamic configuration

This directory is mounted into the Traefik container at `/etc/traefik/dynamic/marketing`.
Keeping it in the repository ensures the bind mount has an existing target so Docker
can start the container even when no marketing overrides are present.
