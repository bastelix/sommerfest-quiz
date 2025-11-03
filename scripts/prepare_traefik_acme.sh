#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
PROJECT_ROOT=$(cd "${SCRIPT_DIR}/.." && pwd)
ACME_DIR="${PROJECT_ROOT}/letsencrypt"
ACME_FILE="${ACME_DIR}/acme.json"

mkdir -p "${ACME_DIR}"

if [[ ! -f "${ACME_FILE}" ]]; then
  touch "${ACME_FILE}"
fi

chmod 600 "${ACME_FILE}"

echo "Ensured ${ACME_FILE} exists with permissions 600."
