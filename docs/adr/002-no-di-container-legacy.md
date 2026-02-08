# ADR-002: Manual service wiring without DI container (legacy)

## Status

Accepted (migration planned)

## Context

The application was bootstrapped as a slim PHP project where all services are
instantiated manually inside `src/routes.php`. At the time, a DI container was
considered unnecessary overhead for the initial scope.

As the project grew to 80+ services and 300+ routes, the manual wiring became
a maintenance burden: every new dependency requires editing routes.php, services
are eagerly instantiated on every request, and duplicate instantiations have
appeared in 18+ locations.

## Decision

The current approach is documented as legacy. New services must accept all
dependencies through constructor injection so they are container-ready.

A migration to a lightweight PSR-11 container (PHP-DI or Slim's built-in
container) is planned as Phase 3 of the architecture roadmap
(see `docs/architecture-tasks.md`).

## Consequences

- Adding a new service currently requires modifying routes.php.
- All services are created eagerly, even if the request only needs a subset.
- The transition to a DI container can happen incrementally: register one
  service at a time while the manual wiring continues for unregistered services.
