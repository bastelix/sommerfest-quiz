#!/usr/bin/env bash
set -euo pipefail

# Runs the same PHPStan check that is executed during the production Docker build.
#
# Usage:
#   ./scripts/check_phpstan.sh
#
# Notes:
# - This relies on Docker build cache, so after the first run it should be fast.
# - If you want a fully clean run: export NO_CACHE=1

IMAGE_TAG=${IMAGE_TAG:-edocs-cloud:phpstan-check}

BUILD_ARGS=()
if [[ "${NO_CACHE:-}" == "1" ]]; then
  BUILD_ARGS+=(--no-cache)
fi

# Build will fail if PHPStan fails (Dockerfile runs phpstan during build).
docker build "${BUILD_ARGS[@]}" -t "$IMAGE_TAG" .

echo "PHPStan OK (via docker build): $IMAGE_TAG"
