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
assert_status 200 "/purchasing"
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
assert_status 401 "/api/v1/sales/proformas"
assert_status 401 "/api/v1/sales/proformas" POST
assert_status 401 "/api/v1/sales/proformas/00000000-0000-0000-0000-000000000001/export"
assert_status 401 "/api/v1/purchasing/orders"
assert_status 401 "/api/v1/purchasing/orders" POST
assert_status 401 "/api/v1/purchasing/orders/00000000-0000-0000-0000-000000000001/confirm" POST
assert_status 401 "/api/v1/purchasing/receipts"
assert_status 401 "/api/v1/purchasing/receipts" POST
assert_status 401 "/api/v1/purchasing/receipts/00000000-0000-0000-0000-000000000001/post" POST
assert_status 401 "/api/v1/purchasing/receipts/00000000-0000-0000-0000-000000000001/reverse" POST
assert_status 401 "/api/v1/purchasing/invoices"
assert_status 401 "/api/v1/purchasing/invoices" POST
assert_status 401 "/api/v1/purchasing/match-preview?mode=1&sourceDocumentPublicId=00000000-0000-0000-0000-000000000001"
assert_status 401 "/api/v1/purchasing/return-source-preview?goodsReceiptPublicId=00000000-0000-0000-0000-000000000001"
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

OPENAPI_FILE="$(mktemp)"
trap 'rm -f "$OPENAPI_FILE"' EXIT
curl -fsS --connect-timeout 3 --max-time 8 "$BASE_URL/openapi/v1.json" -o "$OPENAPI_FILE"
python3 - "$OPENAPI_FILE" <<'PY'
import json
import sys

with open(sys.argv[1], encoding="utf-8") as handle:
    doc = json.load(handle)

paths = doc.get("paths", {})
required = {
    "/api/v1/sales/quotes",
    "/api/v1/sales/orders",
    "/api/v1/sales/dispatches",
    "/api/v1/sales/invoices",
    "/api/v1/sales/proformas",
    "/api/v1/purchasing/orders",
    "/api/v1/purchasing/receipts",
    "/api/v1/purchasing/invoices",
    "/api/v1/purchasing/match-preview",
    "/api/v1/purchasing/return-source-preview",
}
missing = sorted(required.difference(paths))
if missing:
    raise SystemExit(f"SMOKE_FAIL|OPENAPI_MISSING_SALES_PATHS={missing}")

forbidden = [
    "/api/v1/sales/invoices/{id}/post",
    "/api/v1/sales/invoices/{id}/reverse",
    "/api/v1/sales/invoices/{id:guid}/post",
    "/api/v1/sales/invoices/{id:guid}/reverse",
]
forbidden += [
    "/api/v1/purchasing/invoices/{id}/post",
    "/api/v1/purchasing/invoices/{id}/reverse",
    "/api/v1/purchasing/invoices/{id:guid}/post",
    "/api/v1/purchasing/invoices/{id:guid}/reverse",
]
present = [path for path in forbidden if path in paths]
if present:
    raise SystemExit(f"SMOKE_FAIL|OPENAPI_FORBIDDEN_INVOICE_AUTHORITY={present}")

print("SMOKE_PASS|OPENAPI_SALES_SURFACE=EXPECTED")
print("SMOKE_PASS|OPENAPI_PURCHASING_SURFACE=EXPECTED")
print("SMOKE_PASS|OPENAPI_INVOICE_POST_REVERSE=ABSENT")
PY

echo "SMOKE_RESULT=PASS"
