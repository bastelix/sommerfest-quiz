#!/usr/bin/env bash
set -euo pipefail

# Smoke-test for the public CMS API contract.
#
# Required env vars:
#   BASE_URL   e.g. https://quizrace.app
#   NAMESPACE  e.g. calhelp
#   TOKEN      Bearer token for that namespace
#
# Optional:
#   SLUG_VALID  default: api-contract-ok
#   SLUG_BAD    default: api-contract-bad

BASE_URL=${BASE_URL:?missing BASE_URL}
NAMESPACE=${NAMESPACE:?missing NAMESPACE}
TOKEN=${TOKEN:?missing TOKEN}

SLUG_VALID=${SLUG_VALID:-api-contract-ok}
SLUG_BAD=${SLUG_BAD:-api-contract-bad}

api_put() {
  local slug="$1"
  local json="$2"
  curl -sS -o /tmp/cms_api_check_body.json -w "%{http_code}" \
    -X PUT "${BASE_URL}/api/v1/namespaces/${NAMESPACE}/pages/${slug}" \
    -H "Authorization: Bearer ${TOKEN}" \
    -H "Content-Type: application/json" \
    -d "$json"
}

echo "[1/2] Expect 422 on invalid payload (strict schema)"
CODE=$(api_put "$SLUG_BAD" '{"blocks":[{"id":"event-1","type":"event_highlight","variant":"card","data":{"eventSlug":"demo","catalogSlug":null,"ctaLabel":"x","ctaAriaLabel":"x","showCountdown":false,"showDescription":true}}]}')
if [[ "$CODE" != "422" ]]; then
  echo "Expected 422, got $CODE"
  cat /tmp/cms_api_check_body.json || true
  exit 1
fi

echo "OK: got 422"

echo "[2/2] Expect 200 on valid payload"
CODE=$(api_put "$SLUG_VALID" '{"blocks":[{"id":"block-1","type":"rich_text","variant":"prose","data":{"body":"<p>ok</p>","alignment":"start"}}]}')
if [[ "$CODE" != "200" ]]; then
  echo "Expected 200, got $CODE"
  cat /tmp/cms_api_check_body.json || true
  exit 1
fi

echo "OK: got 200"

echo "CMS API contract smoke-test OK"
