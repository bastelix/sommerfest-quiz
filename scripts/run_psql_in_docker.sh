#!/bin/sh
# Apply PostgreSQL schema and import default data using the Docker container.
# Requires docker compose and the environment variables from .env.
set -e

# detect docker compose command
if command -v docker >/dev/null 2>&1 && docker compose version >/dev/null 2>&1; then
  DOCKER_COMPOSE="docker compose"
elif command -v docker-compose >/dev/null 2>&1; then
  DOCKER_COMPOSE="docker-compose"
else
  echo "docker compose oder docker-compose ist nicht verf\u00fcgbar" >&2
  exit 1
fi

$DOCKER_COMPOSE exec slim sh -c 'psql -h postgres -U "$POSTGRES_USER" -d "$POSTGRES_DB" -f docs/schema.sql && php scripts/import_to_pgsql.php'

