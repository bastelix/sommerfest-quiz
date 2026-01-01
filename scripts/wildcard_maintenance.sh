#!/usr/bin/env bash
set -euo pipefail

BASE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
LOG_DIR="$BASE_DIR/logs"
LOG_FILE="$LOG_DIR/wildcard-maintenance.log"

mkdir -p "$LOG_DIR"

log() {
  printf '%s %s\n' "$(date --iso-8601=seconds)" "$*" | tee -a "$LOG_FILE"
}

require_env() {
  local var_name="$1"
  local value
  value=${!var_name:-}
  if [ -z "$value" ]; then
    log "ERROR: Environment variable $var_name must be set."
    exit 1
  fi
}

log "Starting wildcard maintenance"

if ! command -v php >/dev/null 2>&1; then
  log "ERROR: php binary not found in PATH."
  exit 1
fi

require_env "ACME_SH_BIN"
require_env "ACME_WILDCARD_PROVIDER"
require_env "NGINX_WILDCARD_CERT_DIR"

if [ -n "${ACME_SH_HOME:-}" ]; then
  log "Using ACME_SH_HOME=$ACME_SH_HOME"
fi

run_step() {
  local label="$1"
  shift
  log "Running: $label"
  if ! "$@" 2>&1 | tee -a "$LOG_FILE"; then
    log "ERROR: $label failed"
    exit 1
  fi
}

run_step "Generate nginx zones" php "$BASE_DIR/bin/generate-nginx-zones"
run_step "Provision wildcard certificates" php "$BASE_DIR/bin/provision-wildcard-certificates"

log "Wildcard maintenance completed successfully"
