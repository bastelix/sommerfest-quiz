#!/bin/sh
set -e

# Install composer dependencies if vendor directory is missing
if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist --no-progress
fi

exec "$@"

