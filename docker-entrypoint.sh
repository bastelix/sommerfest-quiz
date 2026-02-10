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

if [ -n "${MARKETING_DOMAINS:-}" ]; then
    echo "Warning: MARKETING_DOMAINS is deprecated and ignored. Manage marketing hosts in the domains table instead." >&2
    unset MARKETING_DOMAINS
fi

echo "Starting entrypoint. Prefer SLIM_VIRTUAL_HOST/SLIM_LETSENCRYPT_HOST and POSTGRES_PASSWORD; legacy variables will be migrated automatically." >&2

if [ -n "${SLIM_VIRTUAL_HOSTS:-}" ] || [ -n "${SLIM_LETSENCRYPT_HOSTS:-}" ]; then
    if [ -n "${SLIM_VIRTUAL_HOSTS:-}" ] && [ -z "${SLIM_VIRTUAL_HOST:-}" ]; then
        echo "Warning: SLIM_VIRTUAL_HOSTS is deprecated; falling back to SLIM_VIRTUAL_HOST." >&2
        export SLIM_VIRTUAL_HOST="$SLIM_VIRTUAL_HOSTS"
    fi

    if [ -n "${SLIM_LETSENCRYPT_HOSTS:-}" ] && [ -z "${SLIM_LETSENCRYPT_HOST:-}" ]; then
        echo "Warning: SLIM_LETSENCRYPT_HOSTS is deprecated; falling back to SLIM_LETSENCRYPT_HOST." >&2
        export SLIM_LETSENCRYPT_HOST="$SLIM_LETSENCRYPT_HOSTS"
    fi

    if [ -z "${VIRTUAL_HOST:-}" ]; then
        export VIRTUAL_HOST="${SLIM_VIRTUAL_HOST:-${SLIM_VIRTUAL_HOSTS:-}}"
    fi

    if [ -z "${LETSENCRYPT_HOST:-}" ]; then
        export LETSENCRYPT_HOST="${SLIM_LETSENCRYPT_HOST:-${SLIM_LETSENCRYPT_HOSTS:-}}"
    fi
fi

if [ -n "${POSTGRES_PASS:-}" ]; then
    echo "Warning: POSTGRES_PASS is deprecated; falling back to POSTGRES_PASSWORD." >&2

    if [ -z "${POSTGRES_PASSWORD:-}" ]; then
        export POSTGRES_PASSWORD="$POSTGRES_PASS"
    fi
fi

php_memory_limit="${PHP_MEMORY_LIMIT:-512M}"
if [ ! -d /usr/local/etc/php/conf.d ]; then
    mkdir -p /usr/local/etc/php/conf.d
fi
printf 'memory_limit = %s\n' "$php_memory_limit" > /usr/local/etc/php/conf.d/zz-memory-limit.ini

php_error_log="${PHP_ERROR_LOG:-/proc/self/fd/2}"
cat <<EOF > /usr/local/etc/php/conf.d/zz-logging.ini
log_errors = On
error_log = ${php_error_log}
display_errors = Off
EOF

start_container_metrics_logging() {
    metrics_interval="${CONTAINER_METRICS_LOG_INTERVAL_SECONDS:-30}"
    case "$metrics_interval" in
        ''|*[!0-9]*)
            metrics_interval=30
            ;;
    esac

    if [ ! -d /sys/fs/cgroup ]; then
        echo "Container metrics logging skipped: /sys/fs/cgroup not available" >&2
        return
    fi

    cgroup_version=1
    if [ -f /sys/fs/cgroup/cgroup.controllers ]; then
        cgroup_version=2
    fi

    if [ "$cgroup_version" -eq 2 ]; then
        memory_current_file="/sys/fs/cgroup/memory.current"
        memory_max_file="/sys/fs/cgroup/memory.max"
        cpu_stat_file="/sys/fs/cgroup/cpu.stat"
        oom_events_file="/sys/fs/cgroup/memory.events"

        if [ ! -f "$memory_current_file" ] || [ ! -f "$cpu_stat_file" ]; then
            echo "Container metrics logging skipped: required cgroup v2 files missing" >&2
            return
        fi

        last_cpu_usage=$(awk '/usage_usec/ {print $2}' "$cpu_stat_file" 2>/dev/null || echo 0)
        last_oom=$(awk '/^oom / {print $2}' "$oom_events_file" 2>/dev/null || echo 0)
        last_oom_kill=$(awk '/^oom_kill / {print $2}' "$oom_events_file" 2>/dev/null || echo 0)

        while true; do
            sleep "$metrics_interval"
            timestamp=$(date -Iseconds)
            memory_current=$(cat "$memory_current_file" 2>/dev/null || echo 0)
            memory_max=$(cat "$memory_max_file" 2>/dev/null || echo "max")
            cpu_usage=$(awk '/usage_usec/ {print $2}' "$cpu_stat_file" 2>/dev/null || echo "$last_cpu_usage")
            cpu_delta=$((cpu_usage - last_cpu_usage))
            cpu_percent=$(awk -v delta="$cpu_delta" -v interval="$metrics_interval" 'BEGIN { if (interval > 0) { printf "%.2f", (delta / (interval * 1000000)) * 100 } else { printf "0.00" } }')

            echo "[$timestamp] container_metrics memory_current_bytes=${memory_current} memory_max_bytes=${memory_max} cpu_usage_usec=${cpu_usage} cpu_percent=${cpu_percent}" >&2

            if [ -f "$oom_events_file" ]; then
                oom=$(awk '/^oom / {print $2}' "$oom_events_file" 2>/dev/null || echo "$last_oom")
                oom_kill=$(awk '/^oom_kill / {print $2}' "$oom_events_file" 2>/dev/null || echo "$last_oom_kill")

                if [ "$oom" -gt "$last_oom" ] || [ "$oom_kill" -gt "$last_oom_kill" ]; then
                    echo "[$timestamp] container_oom_event oom=${oom} oom_kill=${oom_kill}" >&2
                    last_oom=$oom
                    last_oom_kill=$oom_kill
                fi
            fi

            last_cpu_usage=$cpu_usage
        done &

        return
    fi

    memory_current_file="/sys/fs/cgroup/memory/memory.usage_in_bytes"
    memory_limit_file="/sys/fs/cgroup/memory/memory.limit_in_bytes"
    cpu_usage_file="/sys/fs/cgroup/cpu/cpuacct.usage"
    oom_control_file="/sys/fs/cgroup/memory/memory.oom_control"

    if [ ! -f "$memory_current_file" ] || [ ! -f "$cpu_usage_file" ]; then
        echo "Container metrics logging skipped: required cgroup v1 files missing" >&2
        return
    fi

    last_cpu_usage=$(cat "$cpu_usage_file" 2>/dev/null || echo 0)
    last_oom_kill=$(awk '/oom_kill/ {print $2}' "$oom_control_file" 2>/dev/null || echo 0)

    while true; do
        sleep "$metrics_interval"
        timestamp=$(date -Iseconds)
        memory_current=$(cat "$memory_current_file" 2>/dev/null || echo 0)
        memory_max=$(cat "$memory_limit_file" 2>/dev/null || echo 0)
        cpu_usage=$(cat "$cpu_usage_file" 2>/dev/null || echo "$last_cpu_usage")
        cpu_delta=$((cpu_usage - last_cpu_usage))
        cpu_percent=$(awk -v delta="$cpu_delta" -v interval="$metrics_interval" 'BEGIN { if (interval > 0) { printf "%.2f", (delta / (interval * 1000000000)) * 100 } else { printf "0.00" } }')

        echo "[$timestamp] container_metrics memory_current_bytes=${memory_current} memory_max_bytes=${memory_max} cpu_usage_ns=${cpu_usage} cpu_percent=${cpu_percent}" >&2

        if [ -f "$oom_control_file" ]; then
            oom_kill=$(awk '/oom_kill/ {print $2}' "$oom_control_file" 2>/dev/null || echo "$last_oom_kill")
            if [ "$oom_kill" -gt "$last_oom_kill" ]; then
                echo "[$timestamp] container_oom_event oom_kill=${oom_kill}" >&2
                last_oom_kill=$oom_kill
            fi
        fi

        last_cpu_usage=$cpu_usage
    done &
}

ssl_log() {
    log_file="${SSL_LOG_FILE:-/var/www/logs/ssl_provisioning.log}"
    log_dir=$(dirname "$log_file")

    if [ ! -d "$log_dir" ]; then
        mkdir -p "$log_dir" 2>/dev/null || true
    fi

    printf '[%s] %s\n' "$(date -Iseconds)" "$1" >> "$log_file"
}

metrics_enabled=$(printf '%s' "${CONTAINER_METRICS_LOGGING:-true}" | tr '[:upper:]' '[:lower:]')
case "$metrics_enabled" in
    1|true|yes|on)
        start_container_metrics_logging
        ;;
esac

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

    dns_prefilter_mode=$(printf '%s' "${LE_SKIP_DNS_PREFILTER:-warn}" | tr '[:upper:]' '[:lower:]')
    warn_only=1
    case "$dns_prefilter_mode" in
        0|false|no|off|strict)
            warn_only=0
            ;;
    esac

    filtered=""
    skipped_hosts=""
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
            if [ -z "$skipped_hosts" ]; then
                skipped_hosts="$host"
            else
                skipped_hosts="$skipped_hosts,$host"
            fi
        fi
    done
    IFS=$old_ifs

    if [ -n "$skipped_hosts" ]; then
        if [ "$warn_only" -eq 1 ]; then
            echo "Warning: DNS prefilter in warning-only mode; preserving non-resolvable LETSENCRYPT_HOST entries: $skipped_hosts" >&2
            ssl_log "DNS prefilter warning-only; non-resolvable LETSENCRYPT_HOST entries retained: ${skipped_hosts}"
        else
            echo "Warning: LETSENCRYPT_HOST entries skipped for failed DNS resolution: $skipped_hosts" >&2
            ssl_log "Skipping non-resolvable LETSENCRYPT_HOST entries: ${skipped_hosts}"
        fi
    elif [ "$warn_only" -eq 1 ]; then
        ssl_log "DNS prefilter running in warning-only mode (LE_SKIP_DNS_PREFILTER=${dns_prefilter_mode:-<empty>})"
    fi

    if [ "$warn_only" -eq 1 ]; then
        printf '%s' "$sanitized"
        return
    fi

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

# Automatically expose tenant domains in single-container setups. Add a wildcard
# only when explicitly enabled, otherwise keep HTTP-01 compatible apex entries.
single_container_flag=$(printf '%s' "${TENANT_SINGLE_CONTAINER:-}" | tr '[:upper:]' '[:lower:]')
has_dns01_capable_flow() {
    provider=$(printf '%s' "${ACME_WILDCARD_PROVIDER:-}" | tr '[:space:]' ' ' | tr -d '\t\r\n')
    if [ -n "$provider" ]; then
        return 0
    fi

    if env | grep -E '^ACME_WILDCARD_ENV_' >/dev/null 2>&1; then
        return 0
    fi

    return 1
}

has_existing_wildcard_cert() {
    domain="$1"
    if [ -z "$domain" ]; then
        return 1
    fi

    if [ -f "/var/www/certs/${domain}.crt" ] && [ -f "/var/www/certs/${domain}.key" ]; then
        return 0
    fi

    if [ -f "$(dirname "$0")/certs/${domain}.crt" ] && [ -f "$(dirname "$0")/certs/${domain}.key" ]; then
        return 0
    fi

    return 1
}

case "$single_container_flag" in
    1|true|yes|on)
        base_domain="${MAIN_DOMAIN:-${DOMAIN:-}}"
        if [ -n "$base_domain" ]; then
            wildcard_flag=$(printf '%s' "${ENABLE_WILDCARD_SSL:-}" | tr '[:upper:]' '[:lower:]')
            case "$wildcard_flag" in
                1|true|yes|on)
                    wildcard_regex="~^([a-z0-9-]+\.)?${base_domain}\$"
                    append_proxy_host "$wildcard_regex"

                    # Request certificates for the apex and wildcard domains so that
                    # the issued certificate covers all tenants while remaining
                    # compatible with nginx-proxy.
                    append_host_value "LETSENCRYPT_HOST" "$base_domain"
                    append_host_value "LETSENCRYPT_HOST" "*.${base_domain}"
                    ;;
                *)
                    # Only expose the apex domain so HTTP-01 challenges work for the
                    # explicitly listed hosts.
                    append_proxy_host "$base_domain"
                    append_host_value "LETSENCRYPT_HOST" "$base_domain"
                    ;;
            esac
        fi
        ;;
esac

expanded_virtual_host=$(sanitize_host_list "${VIRTUAL_HOST:-}")
expanded_letsencrypt_host=$(sanitize_host_list "${LETSENCRYPT_HOST:-}")
ssl_log "Expanded host lists before filtering: VIRTUAL_HOST=${expanded_virtual_host:-<empty>} LETSENCRYPT_HOST=${expanded_letsencrypt_host:-<empty>}"
echo "Expanded host lists before filtering: VIRTUAL_HOST=${expanded_virtual_host:-<empty>} LETSENCRYPT_HOST=${expanded_letsencrypt_host:-<empty>}" >&2

if [ -n "${LETSENCRYPT_HOST:-}" ]; then
    raw_letsencrypt_hosts=$(sanitize_host_list "$LETSENCRYPT_HOST")
    ssl_log "LETSENCRYPT_HOST raw input: ${raw_letsencrypt_hosts:-<empty>}"

    filtered_hosts=$(filter_certificate_hosts "$raw_letsencrypt_hosts")
    if [ "$filtered_hosts" != "$raw_letsencrypt_hosts" ]; then
        ssl_log "Removed unsupported hosts from LETSENCRYPT_HOST. Before: ${raw_letsencrypt_hosts:-<empty>} After: ${filtered_hosts:-<empty>}"
    fi

    if [ -z "$filtered_hosts" ] && [ -n "$LETSENCRYPT_HOST" ]; then
        echo "Warning: LETSENCRYPT_HOST only contained unsupported nginx regex hosts; clearing value" >&2
    fi

    filtered_hosts=$(filter_resolvable_hosts "$filtered_hosts")
    if [ -n "$raw_letsencrypt_hosts" ] && [ "$filtered_hosts" != "$raw_letsencrypt_hosts" ]; then
        ssl_log "Removed non-resolvable LETSENCRYPT_HOST entries. Before: ${raw_letsencrypt_hosts:-<empty>} After: ${filtered_hosts:-<empty>}"
    fi

    export LETSENCRYPT_HOST="$filtered_hosts"
    ssl_log "Final LETSENCRYPT_HOST after filtering: ${filtered_hosts:-<empty>}"
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
            ssl_log "Persisted LETSENCRYPT_HOST cache: ${normalized_hosts}"
            if [ -n "$NGINX_RELOADER_URL" ]; then
                if curl -fs -X POST -H "X-Token: ${NGINX_RELOAD_TOKEN:-changeme}" "$NGINX_RELOADER_URL" >/dev/null; then
                    echo "Triggered nginx reload for updated certificate host list"
                    ssl_log "Triggered nginx reload for updated certificate host list"
                else
                    echo "Warning: Failed to trigger nginx reload at $NGINX_RELOADER_URL" >&2
                    ssl_log "Failed to trigger nginx reload at $NGINX_RELOADER_URL"
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
    if [ -f scripts/rebuild_namespace_tokens.php ]; then
        echo "Rebuilding namespace token stylesheet"
        php scripts/rebuild_namespace_tokens.php
    fi
    if [ -f scripts/bootstrap_admin_user.php ]; then
        echo "Bootstrapping admin user"
        php scripts/bootstrap_admin_user.php
    fi
    unset PGPASSWORD
fi

ssl_bootstrap=$(printf '%s' "${REQUEST_SSL_ON_STARTUP:-1}" | tr '[:upper:]' '[:lower:]')
case "$ssl_bootstrap" in
    1|true|yes|on)
        if [ -x bin/provision-wildcard-certificates ]; then
            echo "Triggering wildcard certificate provisioning for configured zones"
            bin/provision-wildcard-certificates || true
        fi

        if [ -x bin/generate-nginx-zones ]; then
            echo "Regenerating nginx wildcard server blocks"
            bin/generate-nginx-zones || true
        fi
        ;;
esac

exec $@
