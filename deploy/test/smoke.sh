#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${1:?Base URL is required}"

assert_status() {
  local expected="$1"
  local path="$2"
  local method="${3:-GET}"
  local actual
  actual="$(curl -sS -o /dev/null -w '%{http_code}' --connect-timeout 3 --max-time 8 -X "$method" "$BASE_URL$path")"
  if [ "$actual" != "$expected" ]; then
    echo "SMOKE_FAIL|PATH=$path|EXPECTED=$expected|ACTUAL=$actual"
    return 1
  fi
  echo "SMOKE_PASS|METHOD=$method|PATH=$path|STATUS=$actual"
}

assert_status 200 "/"
assert_status 200 "/components"
assert_status 200 "/proof"
assert_status 200 "/parties/new"
assert_status 200 "/health/live"
assert_status 200 "/health/ready"
assert_status 401 "/api/v1/foundation/context"
assert_status 401 "/api/v1/foundation/proof" POST
assert_status 401 "/api/v1/parties" POST
assert_status 401 "/api/v1/parties/00000000-0000-0000-0000-000000000001/roles" POST
assert_status 401 "/api/v1/parties/00000000-0000-0000-0000-000000000001/roles/CUSTOMER/state" POST
assert_status 401 "/api/v1/parties/00000000-0000-0000-0000-000000000001/tax-identities" POST
assert_status 200 "/openapi/v1.json"

echo "SMOKE_RESULT=PASS"
