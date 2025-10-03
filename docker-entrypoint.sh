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

# Install composer dependencies if autoloader or QR code library is missing
if [ ! -f vendor/autoload.php ] || [ ! -d vendor/chillerlan/php-qrcode ]; then
    composer install --no-interaction --prefer-dist --no-progress
fi

# Ensure log directory exists and is writable
if [ ! -d /var/www/logs ]; then
    mkdir -p /var/www/logs
fi
chown -R www-data:www-data /var/www/logs 2>/dev/null || true

# Ensure backup directory exists and is writable
if [ ! -d /var/www/backup ]; then
    mkdir -p /var/www/backup
fi
chown -R www-data:www-data /var/www/backup 2>/dev/null || true

# Copy default data if no config exists in /var/www/data
if [ ! -f /var/www/data/config.json ] && [ -d /var/www/data-default ]; then
    cp -a /var/www/data-default/. /var/www/data/
fi

# Normalize the Let's Encrypt host list and trigger a proxy reload when it changes.
normalize_hosts() {
    printf '%s' "$1" | tr '\n' ',' | sed -e 's/[[:space:]]//g' -e 's/,\{2,\}/,/g' -e 's/^,//' -e 's/,$//'
}

if [ -n "$LETSENCRYPT_HOST" ]; then
    normalized_hosts=$(normalize_hosts "$LETSENCRYPT_HOST")
    cache_file=/var/www/data/.letsencrypt-hosts

    if [ "$normalized_hosts" != "$LETSENCRYPT_HOST" ]; then
        echo "Warning: LETSENCRYPT_HOST contains whitespace; normalized to '$normalized_hosts'" >&2
    fi

    if [ -n "$normalized_hosts" ]; then
        mkdir -p "$(dirname "$cache_file")"
        if [ ! -f "$cache_file" ] || [ "$(cat "$cache_file" 2>/dev/null)" != "$normalized_hosts" ]; then
            echo "$normalized_hosts" > "$cache_file"
            if [ -n "$NGINX_RELOADER_URL" ]; then
                if curl -fs -X POST -H "X-Token: ${NGINX_RELOAD_TOKEN:-changeme}" "$NGINX_RELOADER_URL" >/dev/null; then
                    echo "Triggered nginx reload for updated certificate host list"
                else
                    echo "Warning: Failed to trigger nginx reload at $NGINX_RELOADER_URL" >&2
                fi
            fi
        fi
    fi
fi

if [ -n "$POSTGRES_DSN" ] && [ -f docs/schema.sql ]; then
    host=$(echo "$POSTGRES_DSN" | sed -n 's/.*host=\([^;]*\).*/\1/p')
    port=$(echo "$POSTGRES_DSN" | sed -n 's/.*port=\([^;]*\).*/\1/p')
    db=${POSTGRES_DB:-$(echo "$POSTGRES_DSN" | sed -n 's/.*dbname=\([^;]*\).*/\1/p')}
    port=${port:-5432}
    export PGPASSWORD="${POSTGRES_PASSWORD:-$POSTGRES_PASS}"

    echo "Waiting for PostgreSQL to become available..."
    timeout=30
    until psql -h "$host" -p "$port" -U "$POSTGRES_USER" -d "$db" -c 'SELECT 1;' >/dev/null 2>&1; do
        if [ $timeout -le 0 ]; then
            echo "PostgreSQL not reachable, aborting." >&2
            exit 1
        fi
        sleep 1
        timeout=$((timeout-1))
    done

    echo "PostgreSQL is available"

    echo "Checking for existing PostgreSQL schema..."
    schema_present=$(psql -h "$host" -p "$port" -U "$POSTGRES_USER" -d "$db" -tAc "SELECT EXISTS (SELECT 1 FROM pg_tables WHERE schemaname='public' AND tablename='config');")
    if [ "$schema_present" = "t" ]; then
        echo "Schema already present, skipping initialization"
    else
        echo "Applying PostgreSQL schema"
        psql -h "$host" -p "$port" -U "$POSTGRES_USER" -d "$db" -f docs/schema.sql
        echo "Schema initialized"
        if [ -f scripts/import_to_pgsql.php ]; then
            echo "Importing default data"
            php scripts/import_to_pgsql.php
            echo "Data import complete"
        fi
    fi
    if [ -f scripts/run_migrations.php ]; then
        echo "Running migrations"
        php scripts/run_migrations.php
    fi
    if [ -f scripts/bootstrap_admin_user.php ]; then
        echo "Bootstrapping admin user"
        php scripts/bootstrap_admin_user.php
    fi
    unset PGPASSWORD
fi

exec $@

