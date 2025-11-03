# Marketing configuration mount point

This directory acts as the bind mount target for the marketing-specific Traefik
configuration that is generated at runtime. Keeping the folder in the repository
prevents Docker from attempting to create it inside the container when the
parent `/etc/traefik/dynamic` directory is mounted read-only, which would
otherwise cause the Traefik service to fail during startup.
