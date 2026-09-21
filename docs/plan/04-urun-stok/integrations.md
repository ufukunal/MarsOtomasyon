# Product / Inventory Master Integration Contract

Status: FROZEN planning contract. Provider-specific capabilities are implementation-time verification items.

## 1. General boundary

Product/Inventory master changes commit to PostgreSQL authority first.

Reliable downstream propagation:
master transaction → outbox → Worker → consumer/provider adapter.

External provider success does not determine local Product master commit unless a future explicit business workflow requires verified provider acceptance.

## 2. Candidate master-data events

- ProductCreated
- ProductChanged
- ProductActivated
- ProductDeactivated
- ProductVariantChanged
- ProductUomChanged
- ProductBarcodeChanged
- ProductCategoryChanged
- WarehouseChanged
- LocationChanged
- LotMetadataChanged

Inventory physical events are owned by Inventory/Warehouse transactional workflows, not Product master:
- InventoryMovementPosted
- InventoryMovementReversed
- ReservationChanged
- Transfer/Count events later.

Payload principle:
- stable public identity;
- company/scope;
- version;
- minimum data needed;
- no provider-specific mega payload.

## 3. Sales integration

Sales consumes:
- active SELLABLE Product/Variant;
- accepted UOM/conversion;
- barcode/lookup;
- stock/availability projection where needed.

Sales freezes historical Product/UOM snapshots.

Product master changes:
- affect future lookup;
- never mutate posted Sales documents.
- do not post stock.

## 4. Purchasing integration

Future Purchasing consumes:
- active PURCHASABLE Product/Variant/UOM;
- Supplier/Party remains separate authority;
- Goods Receipt STOCK IN belongs Purchase/Warehouse workflow.

PLAN-004 does not define receiving tolerances or supplier invoice match.

## 5. Warehouse integration

Warehouse consumes:
- stockable identity;
- UOM/base normalization;
- barcode;
- Warehouse/Location;
- tracking strategy;
- Lot/Serial;
- disposition/status.

Barcode/scan may select an identity but cannot itself create physical stock without an authorized Warehouse command/posting.

## 6. Finance / valuation integration

Finance/Costing consumes:
- Product/Variant/classification references;
- physical Inventory Ledger source information;
- later accepted valuation inputs.

Forbidden:
- provider/Product field silently becoming inventory valuation authority.
- current Product master cost overwriting historical financial value.

## 7. GS1 / barcode integration

When code namespace = GS1:
- structural/check-digit validation may use current official GS1 rules.
- GTIN identifies a specific trade item/package level.
- Verified by GS1 or another official/provider lookup, if implemented, is verification metadata and not Mars Product ownership.

Non-GS1 barcode:
- use explicit Mars namespace/type;
- still enforce one active mapping → one trade identity/UOM.

Current GS1 rules must be reverified at implementation time.

## 8. External commerce/product mappings

Marketplace/WooCommerce/provider product IDs:
- map to Product/Variant;
- provider account/source context included;
- import retries idempotent;
- duplicate external IDs conflict or resolve through staging/mapping;
- provider does not create parallel stock/accounting truth.

Exact provider behavior belongs PLAN-009/Commerce planning.

## 9. Lot / Serial import/scanning

External scans may provide:
- GTIN/Product code;
- Lot;
- Serial;
- expiry;
- quantity.

Rules:
- scan validates against Product tracking strategy;
- malformed/mismatched lot/serial rejected before physical post;
- provider/scanner retry cannot create duplicate physical movement;
- offline scanning later requires durable conflict/idempotency behavior.

## 10. Inventory projection/cache

Search/stock projections and Valkey may cache:
- lookup;
- aggregate on-hand/available;
- barcode resolution metadata.

Rules:
- cache flush must not lose authoritative quantity.
- cache staleness cannot authorize over-processing where authoritative check is required.
- write path validates PostgreSQL/ledger/reservation truth.

## 11. Error/reconciliation

Observe:
- product mapping conflict;
- duplicate barcode;
- stale UOM mapping;
- invalid/inactive Product;
- lot/serial mismatch;
- external sync retry/error;
- projection lag.

Reconciliation compares provider/projection state to Mars authority; it does not overwrite ledger blindly.
