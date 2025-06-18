#!/bin/sh
# Apply PostgreSQL schema and import default data using the Docker container.
# Requires docker-compose and the environment variables from .env.
set -e

docker-compose exec slim bash -c 'psql -h postgres -U "$POSTGRES_USER" -d "$POSTGRES_DB" -f docs/schema.sql && php scripts/import_to_pgsql.php'

