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

php_memory_limit="${PHP_MEMORY_LIMIT:-512M}"
printf 'memory_limit = %s\n' "$php_memory_limit" > /usr/local/etc/php/conf.d/zz-memory-limit.ini

# Normalize comma-separated host lists by removing whitespace and collapsing
# duplicate separators.
normalize_host_list() {
    printf '%s' "$1" | tr '\n' ',' | sed -e 's/[[:space:]]//g' -e 's/,\{2,\}/,/g' -e 's/^,//' -e 's/,$//'
}

normalized_virtual_host=$(normalize_host_list "${VIRTUAL_HOST:-}")
if [ -z "$normalized_virtual_host" ]; then
    echo "Error: VIRTUAL_HOST is empty after normalization. Check .env for VIRTUAL_HOST, DOMAIN, MAIN_DOMAIN, or SLIM_VIRTUAL_HOST." >&2
    exit 1
fi
export VIRTUAL_HOST="$normalized_virtual_host"

# Normalize comma-separated host lists by removing whitespace and collapsing
# duplicate separators.
sanitize_host_list() {
    normalize_host_list "$1"
}

is_regex_host() {
    case "$1" in
        \~*)
            return 0
            ;;
        *)
            return 1
            ;;
    esac
}

filter_certificate_hosts() {
    sanitized=$(sanitize_host_list "$1")
    if [ -z "$sanitized" ]; then
        printf '%s' "$sanitized"
        return
    fi

    filtered=""
    old_ifs=$IFS
    IFS=','
    for host in $sanitized; do
        if [ -z "$host" ]; then
            continue
        fi

        if is_regex_host "$host"; then
            continue
        fi

        if [ -z "$filtered" ]; then
            filtered="$host"
            continue
        fi

        if printf '%s' "$filtered" | tr ',' '\n' | grep -Fx -- "$host" >/dev/null 2>&1; then
            continue
        fi

        filtered="$filtered,$host"
    done
    IFS=$old_ifs

    printf '%s' "$filtered"
}

filter_resolvable_hosts() {
    sanitized=$(sanitize_host_list "$1")
    if [ -z "$sanitized" ]; then
        printf '%s' "$sanitized"
        return
    fi

    filtered=""
    old_ifs=$IFS
    IFS=','
    for host in $sanitized; do
        if [ -z "$host" ]; then
            continue
        fi

        if getent hosts "$host" >/dev/null 2>&1; then
            if [ -z "$filtered" ]; then
                filtered="$host"
                continue
            fi

            filtered="$filtered,$host"
        else
            echo "Warning: LETSENCRYPT_HOST entry '$host' does not resolve; skipping" >&2
        fi
    done
    IFS=$old_ifs

    printf '%s' "$filtered"
}

append_host_value() {
    var_name="$1"
    host_to_add="$2"
    current=$(printenv "$var_name")
    sanitized=$(sanitize_host_list "$current")

    if [ -z "$host_to_add" ]; then
        return
    fi

    if [ -n "$sanitized" ] && printf '%s' "$sanitized" | tr ',' '\n' | grep -Fx -- "$host_to_add" >/dev/null 2>&1; then
        return
    fi

    if [ -n "$sanitized" ]; then
        export "$var_name=${sanitized},${host_to_add}"
    else
        export "$var_name=$host_to_add"
    fi
}

append_proxy_host() {
    host="$1"

    append_host_value "VIRTUAL_HOST" "$host"

    if ! is_regex_host "$host"; then
        append_host_value "LETSENCRYPT_HOST" "$host"
    fi
}

# Automatically expose all tenant subdomains in single-container setups so the
# proxy requests a matching wildcard certificate.
single_container_flag=$(printf '%s' "${TENANT_SINGLE_CONTAINER:-}" | tr '[:upper:]' '[:lower:]')
case "$single_container_flag" in
    1|true|yes|on)
        base_domain="${MAIN_DOMAIN:-${DOMAIN:-}}"
        if [ -n "$base_domain" ]; then
            wildcard_regex="~^([a-z0-9-]+\.)?${base_domain}\$"
            append_proxy_host "$wildcard_regex"

            # Request certificates for the apex and wildcard domains so that
            # the issued certificate covers all tenants while remaining
            # compatible with nginx-proxy.
            append_host_value "LETSENCRYPT_HOST" "$base_domain"
            append_host_value "LETSENCRYPT_HOST" "*.${base_domain}"
        fi
        ;;
esac

if [ -n "${LETSENCRYPT_HOST:-}" ]; then
    filtered_hosts=$(filter_certificate_hosts "$LETSENCRYPT_HOST")
    if [ -z "$filtered_hosts" ] && [ -n "$LETSENCRYPT_HOST" ]; then
        echo "Warning: LETSENCRYPT_HOST only contained unsupported nginx regex hosts; clearing value" >&2
    fi
    filtered_hosts=$(filter_resolvable_hosts "$filtered_hosts")
    export LETSENCRYPT_HOST="$filtered_hosts"
fi

redact_host_list() {
    sanitized=$(sanitize_host_list "$1")
    if [ -z "$sanitized" ]; then
        printf '%s' "<empty>"
        return
    fi
    count=$(printf '%s' "$sanitized" | tr ',' '\n' | sed '/^$/d' | wc -l | tr -d ' ')
    printf '%s' "<redacted:${count}>"
}

echo "VIRTUAL_HOST (redacted): $(redact_host_list "${VIRTUAL_HOST:-}")"
echo "LETSENCRYPT_HOST (redacted): $(redact_host_list "${LETSENCRYPT_HOST:-}")"

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

if [ -n "$LETSENCRYPT_HOST" ]; then
    normalized_hosts=$(normalize_host_list "$LETSENCRYPT_HOST")
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
    wait_timeout=${POSTGRES_WAIT_TIMEOUT_SECONDS:-30}
    retry_flag=$(printf '%s' "${POSTGRES_WAIT_RETRY_ENABLED:-}" | tr '[:upper:]' '[:lower:]')
    backoff_seconds=${POSTGRES_WAIT_RETRY_BACKOFF_SECONDS:-5}

    while :; do
        timeout=$wait_timeout
        until psql -h "$host" -p "$port" -U "$POSTGRES_USER" -d "$db" -c 'SELECT 1;' >/dev/null 2>&1; do
            if [ "$timeout" -le 0 ]; then
                echo "PostgreSQL not reachable after ${wait_timeout}s (host=${host:-?} port=$port db=$db)." >&2
                case "$retry_flag" in
                    1|true|yes|on)
                        echo "Retrying PostgreSQL wait in ${backoff_seconds}s..." >&2
                        sleep "$backoff_seconds"
                        ;;
                    *)
                        exit 1
                        ;;
                esac
                break
            fi
            sleep 1
            timeout=$((timeout-1))
        done
        if [ "$timeout" -gt 0 ]; then
            break
        fi
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
