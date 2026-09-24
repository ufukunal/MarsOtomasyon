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
assert_status 200 "/parties"
assert_status 200 "/parties/new"
assert_status 200 "/products"
assert_status 200 "/inventory"
assert_status 200 "/sales"
assert_status 200 "/health/live"
assert_status 200 "/health/ready"
assert_status 401 "/api/v1/foundation/context"
assert_status 401 "/api/v1/foundation/proof" POST
assert_status 401 "/api/v1/parties"
assert_status 401 "/api/v1/parties" POST
assert_status 401 "/api/v1/products"
assert_status 401 "/api/v1/products" POST
assert_status 401 "/api/v1/products/uoms"
assert_status 401 "/api/v1/products/uoms" POST
assert_status 401 "/api/v1/products/categories"
assert_status 401 "/api/v1/products/00000000-0000-0000-0000-000000000001"
assert_status 401 "/api/v1/products/00000000-0000-0000-0000-000000000001" PUT
assert_status 401 "/api/v1/products/00000000-0000-0000-0000-000000000001/variants" POST
assert_status 401 "/api/v1/products/00000000-0000-0000-0000-000000000001/uoms" POST
assert_status 401 "/api/v1/products/00000000-0000-0000-0000-000000000001/barcodes" POST
assert_status 401 "/api/v1/products/00000000-0000-0000-0000-000000000001/external-mappings" POST
assert_status 401 "/api/v1/inventory/warehouses"
assert_status 401 "/api/v1/inventory/warehouses" POST
assert_status 401 "/api/v1/inventory/locations"
assert_status 401 "/api/v1/inventory/locations" POST
assert_status 401 "/api/v1/inventory/stock"
assert_status 401 "/api/v1/inventory/positions"
assert_status 401 "/api/v1/inventory/movements"
assert_status 401 "/api/v1/inventory/lots"
assert_status 401 "/api/v1/inventory/lots" POST
assert_status 401 "/api/v1/inventory/serials/00000000-0000-0000-0000-000000000001"
assert_status 401 "/api/v1/inventory/reservations"
assert_status 401 "/api/v1/inventory/warehouses/00000000-0000-0000-0000-000000000001/access-grants" POST
assert_status 401 "/api/v1/inventory/warehouses/00000000-0000-0000-0000-000000000001/access-grants/revoke" POST
assert_status 401 "/api/v1/sales/quotes"
assert_status 401 "/api/v1/sales/quotes" POST
assert_status 401 "/api/v1/sales/orders"
assert_status 401 "/api/v1/sales/orders" POST
assert_status 401 "/api/v1/sales/reservations" POST
assert_status 401 "/api/v1/sales/dispatches"
assert_status 401 "/api/v1/sales/dispatches" POST
assert_status 401 "/api/v1/sales/dispatches/00000000-0000-0000-0000-000000000001/post" POST
assert_status 401 "/api/v1/sales/dispatches/00000000-0000-0000-0000-000000000001/reverse" POST
assert_status 401 "/api/v1/sales/invoices"
assert_status 401 "/api/v1/sales/invoices" POST
assert_status 401 "/api/v1/parties/00000000-0000-0000-0000-000000000001"
assert_status 401 "/api/v1/parties/00000000-0000-0000-0000-000000000001" PUT
assert_status 401 "/api/v1/parties/00000000-0000-0000-0000-000000000001/contacts" POST
assert_status 401 "/api/v1/parties/00000000-0000-0000-0000-000000000001/addresses" POST
assert_status 401 "/api/v1/parties/00000000-0000-0000-0000-000000000001/external-mappings" POST
assert_status 401 "/api/v1/parties/00000000-0000-0000-0000-000000000001/merge" POST
assert_status 401 "/api/v1/parties/00000000-0000-0000-0000-000000000001/deactivate" POST
assert_status 401 "/api/v1/parties/00000000-0000-0000-0000-000000000001/roles" POST
assert_status 401 "/api/v1/parties/00000000-0000-0000-0000-000000000001/roles/CUSTOMER/state" POST
assert_status 401 "/api/v1/parties/00000000-0000-0000-0000-000000000001/tax-identities" POST
assert_status 200 "/openapi/v1.json"

echo "SMOKE_RESULT=PASS"
