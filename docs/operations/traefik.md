# Traefik configuration

The stack now relies on Traefik v2.x for edge routing. The static options live in
`config/traefik/traefik.yml` and are mounted into the reverse proxy container by
`docker-compose.yml`. Static configuration keeps HTTP and HTTPS entry points,
redirects insecure requests to TLS, and configures the Let's Encrypt ACME resolver.

## Default middlewares

Reusable middlewares are defined in `config/traefik/dynamic/middlewares.yml`.
They provide the former nginx defaults in a Traefik-native format:

- `quizrace-https-redirect` – redirects HTTP routers to HTTPS.
- `quizrace-security-headers` – adds HSTS, frame protection, and the other
  security headers emitted by the previous nginx template.
- `quizrace-body-limit-5m`, `quizrace-body-limit-10m`, and
  `quizrace-body-limit-50m` – buffering middlewares that enforce request body
  limits roughly equivalent to nginx' `client_max_body_size` directive.

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

Router and service definitions moved from the nginx docker-gen template into
Docker labels. Each service declares the HTTPS router, HTTP redirect router, and
corresponding load balancer target. See `docker-compose.yml` for working
examples for the `slim` application container and the optional Adminer utility.

Add more routers by copying the existing label pattern and adjusting the router
name and rule. Multiple tenants can share the same service by pointing their
router definitions to the same load balancer (`quizrace` in the default compose
file).
