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

                clean_token=$(printf '%s' "$clean_token" | tr -d '<>"'"'"'')
                # Drop query strings that often appear in copied mailto links.
                clean_token=${clean_token%%\?*}
                clean_token=${clean_token%%\#*}

                # Normalise leading/trailing separators commonly used in
                # signatures. We include punctuation like dots or exclamation
                # marks because the address may be followed by a sentence.
                clean_token=$(printf '%s' "$clean_token" | sed 's/^[[:space:],;:()]*//; s/[[:space:],;:().!?-]*$//')

                if printf '%s' "$clean_token" | grep -Eq '^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$'; then
                    printf '%s' "$clean_token"
                    return 0
                fi
                ;;
        esac
    done

    return 1
}

sanitize_domain() {
    # Normalise DOMAIN and MAIN_DOMAIN values which are frequently copied with
    # schemes or trailing paths. Traefik expects bare hostnames in the router
    # rules, so we strip common wrappers and validate the remaining label.
    input=$(printf '%s' "$1" | tr '\r\n' ' ')
    normalized=$(printf '%s' "$input" | tr ',;' ' ')

    for token in $normalized; do
        clean_token=$(printf '%s' "$token" | tr -d '<>"'"'"'')
        clean_token=$(printf '%s' "$clean_token" | tr '[:upper:]' '[:lower:]')

        case "$clean_token" in
            *://*)
                clean_token=${clean_token#*://}
                ;;
        esac

        clean_token=${clean_token#*@}
        clean_token=${clean_token%%/*}
        clean_token=${clean_token%%\?*}
        clean_token=${clean_token%%\#*}
        clean_token=${clean_token#*[[]}
        clean_token=${clean_token%]*}
        clean_token=$(printf '%s' "$clean_token" | sed 's/^[[:space:].-]*//; s/[[:space:]]*$//; s/\.$//')

        if printf '%s' "$clean_token" | grep -Eq '^[[:alnum:]]([[:alnum:]-]{0,61}[[:alnum:]])?(\.[[:alnum:]]([[:alnum:]-]{0,61}[[:alnum:]])?)*$'; then
            printf '%s' "$clean_token"
            return 0
        fi
    done

    return 1
}

# Normalise domain-related environment variables before Traefik starts. The
# ACME resolver aborts when router rules reference malformed domains, so we
# attempt to extract a clean hostname from the provided value. If no usable
# hostname is found we abort early with a descriptive error.
sanitize_domain_var() {
    var_name=$1
    raw_value=$(printenv "$var_name" 2>/dev/null || true)

    if [ -z "$raw_value" ]; then
        return 0
    fi

    sanitized_value=""
    if sanitized_value=$(sanitize_domain "$raw_value"); then
        export "$var_name"="$sanitized_value"
        echo "Info: Verwende $var_name=$sanitized_value" >&2
        return 0
    fi

    echo "Fehler: Der Wert in $var_name enthält keinen gültigen Hostnamen. Entferne Schemata wie https:// und wiederhole den Start." >&2
    exit 1
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
    echo "Info: Verwende LETSENCRYPT_EMAIL=$sanitized_email" >&2
else
    if [ -n "$raw_email" ]; then
        echo "Warnung: Konnte die in LETSENCRYPT_EMAIL oder LE_EMAIL angegebene Adresse nicht auswerten. Bitte überprüfe den Wert." >&2
    fi
    echo "Fehler: Traefik benötigt eine gültige Kontaktadresse in LETSENCRYPT_EMAIL (oder LE_EMAIL). Setze die Variable in .env und starte den Container neu." >&2
    exit 1
fi

sanitize_domain_var DOMAIN
sanitize_domain_var MAIN_DOMAIN

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
