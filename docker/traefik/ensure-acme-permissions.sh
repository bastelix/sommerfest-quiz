#!/bin/sh
set -eu

ACME_STORAGE="/letsencrypt/acme.json"

sanitize_email() {
    # Replace common separators with whitespace and iterate over the resulting
    # tokens to find a syntactically valid email address. The pattern is
    # intentionally simple; Traefik only needs the first valid address.
    input=$(printf '%s' "$1" | tr '\r\n' ' ')
    normalized=$(printf '%s' "$input" | tr ',;' ' ')

    for token in $normalized; do
        case "$token" in
            *"@"*)
                clean_token=$token
                # Strip wrappers such as "mailto:" links or quoted names so that
                # common contact notations like "Team <admin@example.com>" work.
                case "$clean_token" in
                    [Mm][Aa][Ii][Ll][Tt][Oo]:*)
                        clean_token=${clean_token#*:}
                        ;;
                esac

                clean_token=$(printf '%s' "$clean_token" | tr -d '<>"')
                clean_token=${clean_token//\'/}
                clean_token=$(printf '%s' "$clean_token" | sed 's/^[[:space:],;:()]*//; s/[[:space:],;:()]*$//')

                if printf '%s' "$clean_token" | grep -Eq '^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$'; then
                    printf '%s' "$clean_token"
                    return 0
                fi
                ;;
        esac
    done

    return 1
}

if [ ! -e "$ACME_STORAGE" ]; then
    # The default Traefik entrypoint will take care of creating the file if it
    # is missing, but we ensure it exists so the permission fix below can run
    # without errors when bind mounting from the host.
    touch "$ACME_STORAGE" 2>/dev/null || true
fi

# Traefik refuses to use ACME storage files that have group or world access.
# When the file lives on a host filesystem that ignores chmod calls (e.g. some
# network shares on macOS/Windows), the operation may fail. We attempt to
# normalise the permissions and keep Traefik's startup scripts in control over
# how to proceed.
if ! chmod 600 "$ACME_STORAGE" 2>/dev/null; then
    echo "Warnung: Konnte die Berechtigungen von $ACME_STORAGE nicht auf 600 setzen." >&2
fi

raw_email=${LETSENCRYPT_EMAIL:-}
if [ -z "$raw_email" ]; then
    raw_email=${LE_EMAIL:-}
fi
sanitized_email=""
if [ -n "$raw_email" ] && sanitized_email=$(sanitize_email "$raw_email"); then
    export LETSENCRYPT_EMAIL="$sanitized_email"
    if [ -n "${LE_EMAIL:-}" ]; then
        export LE_EMAIL="$sanitized_email"
    fi
else
    if [ -n "$raw_email" ]; then
        echo "Warnung: Konnte die in LETSENCRYPT_EMAIL oder LE_EMAIL angegebene Adresse nicht auswerten. Bitte überprüfe den Wert." >&2
    fi
    sanitized_email=""
    unset LETSENCRYPT_EMAIL || true
    unset LE_EMAIL || true
fi

updated_args=""
for arg in "$@"; do
    case "$arg" in
        --certificatesresolvers.letsencrypt.acme.email=*)
            if [ -n "$sanitized_email" ]; then
                arg="--certificatesresolvers.letsencrypt.acme.email=${sanitized_email}"
            else
                # Skip invalid email argument so Traefik does not receive a
                # malformed contact address.
                continue
            fi
            ;;
    esac

    if [ -z "$updated_args" ]; then
        updated_args="$arg"
    else
        updated_args="$updated_args\n$arg"
    fi
done

set --
if [ -n "$updated_args" ]; then
    # Restore the argument list with the (potentially) updated email flag.
    IFS='\n'
    for arg in $updated_args; do
        set -- "$@" "$arg"
    done
    unset IFS
fi

exec /entrypoint.sh "$@"
