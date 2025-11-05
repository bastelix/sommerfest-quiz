# Traefik configuration

The stack now relies on Traefik v2.x for edge routing. Static options live in
`config/traefik/traefik.yml` and are mounted into the reverse proxy container by
`docker-compose.yml`. The file defines the entry points (`web`, `websecure`,
`traefik`), configures the ACME resolver, and enables the dashboard/API surface.
Everything under `config/traefik/dynamic/` is loaded as a watched file provider.
Two files ship with the repository:

- `config/traefik/dynamic/middlewares.yml` – reusable security header and body
  limit middlewares.
- `config/traefik/dynamic/api.yml.tmpl` – routes the dashboard and API on entry point
`traefik`. The insecure dashboard on port `:8080` is disabled, therefore a
  router must be published explicitly. Set `TRAEFIK_DASHBOARD_RULE` to expose
  the dashboard under a host rule and supply credentials via
  `TRAEFIK_API_BASICAUTH` to enforce HTTP basic authentication for both API and
  dashboard.

## Default middlewares

Reusable middlewares are defined in `config/traefik/dynamic/middlewares.yml`.
They mirror the legacy reverse-proxy defaults in a Traefik-native format:

- `quizrace-https-redirect` – redirects HTTP routers to HTTPS.
- `quizrace-security-headers` – adds HSTS, frame protection, and the other
  security headers emitted by the legacy reverse-proxy template.
- `quizrace-body-limit-5m`, `quizrace-body-limit-10m`, and
  `quizrace-body-limit-50m` – buffering middlewares that enforce request body
  limits roughly equivalent to the previous proxy's `client_max_body_size`
  directive.

Attach middlewares in docker-compose labels to keep tenant-specific changes in
service definitions. For example, the default QuizRace application router uses:

```
traefik.http.routers.quizrace-secure.middlewares=quizrace-security-headers@file,quizrace-body-limit-50m@file
```

To tighten the upload limit for a single tenant you can switch the middleware in
place:

```
traefik.http.routers.quizrace-secure.middlewares=quizrace-security-headers@file,quizrace-body-limit-10m@file
```

## Router and service labels

Router and service definitions moved from the legacy docker-gen template into
Docker labels. Each service declares the HTTPS router, HTTP redirect router, and
corresponding load balancer target. See `docker-compose.yml` for working
examples for the `slim` application container and the optional Adminer utility.

Add more routers by copying the existing label pattern and adjusting the router
name and rule. Multiple tenants can share the same service by pointing their
router definitions to the same load balancer (`quizrace` in the default compose
file). After changes run:

```
docker compose exec traefik curl -sf http://localhost:8080/api/http/routers | jq 'keys'
docker compose exec traefik curl -sf http://localhost:8080/api/tls/certificates | jq '.certificates | length'
```

The commands verify that Traefik sees the new routers and has certificates in
place. When the dashboard is published via `TRAEFIK_DASHBOARD_RULE`, the same
information is available graphically at `/dashboard/`.
