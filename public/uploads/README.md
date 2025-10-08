# calServer marketing media

The repository does **not** track binary marketing assets. Deployments must provide the calServer module loops and posters through the shared uploads directory at runtime (for example via an object store sync or manual upload on the server).

When editors update the marketing page in the admin area they can continue to reference files from `/uploads`. Ensure that the following filenames exist in production so the HTML embeds resolve without 404s:

| Module ID | Expected loop | Expected poster |
|-----------|---------------|-----------------|
| Geräteverwaltung & Historie | `calserver-module-device-management.mp4` | `calserver-module-device-management.webp` |
| Kalender & Ressourcen | `calserver-module-calendar-resources.mp4` | `calserver-module-calendar-resources.webp` |
| Auftrags- & Ticketverwaltung | `calserver-module-order-ticketing.mp4` | `calserver-module-order-ticketing.webp` |
| Self-Service & Extranet | `calserver-module-self-service.mp4` | `calserver-module-self-service.webp` |

During local development you can drop lightweight placeholders with those filenames into this directory; they will be ignored by Git but served by the development web server.
