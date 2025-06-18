#!/bin/sh
set -e

# Load variables from .env if available and not already set
if [ -r .env ]; then
    while IFS='=' read -r key value; do
        case "$key" in ''|\#*) continue ;; esac
        if [ -z "$(printenv "$key")" ]; then
            export "$key=$value"
        fi
    done < .env
fi

# Install composer dependencies if vendor directory is missing
if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist --no-progress
fi

if [ -n "$POSTGRES_DSN" ] && [ -f docs/schema.sql ]; then
    echo "Initializing PostgreSQL schema"
    host=$(echo "$POSTGRES_DSN" | sed -n 's/.*host=\([^;]*\).*/\1/p')
    db=${POSTGRES_DB:-$(echo "$POSTGRES_DSN" | sed -n 's/.*dbname=\([^;]*\).*/\1/p')}
    export PGPASSWORD="$POSTGRES_PASS"
    psql -h "$host" -U "$POSTGRES_USER" -d "$db" -f docs/schema.sql >/dev/null
    unset PGPASSWORD
    if [ -f scripts/import_to_pgsql.php ]; then
        php scripts/import_to_pgsql.php >/dev/null
    fi
fi

exec "$@"

