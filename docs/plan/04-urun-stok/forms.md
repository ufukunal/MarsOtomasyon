# Product / Inventory Master UI / Form Contract

Status: FROZEN — PLAN-004

## 1. Personas

Primary:
- Sales/Purchasing expert using F2 Product lookup;
- Product master-data administrator;
- warehouse operator/manager;
- inventory controller;
- finance/accounting reviewer for read-only valuation context where later allowed;
- mobile scanning user.

## 2. Product list

Columns/filters:
- Product Code;
- Variant/SKU summary where applicable;
- name;
- kind GOODS/SERVICE;
- SELLABLE/PURCHASABLE/STOCKABLE capabilities;
- Base UOM;
- primary category;
- tracking strategy;
- ACTIVE/INACTIVE;
- current stock projection only as clearly read-only derived data when requested.

Primary action:
New Product.

Search:
- Product/Variant code;
- name;
- barcode/GTIN;
- category;
- state/capability.

Never expose editable `stock_quantity`.

## 3. Product detail

Sections:
1. Identity
2. Capabilities
3. Variants
4. UOMs
5. Barcodes
6. Classification
7. Inventory setup
8. Stock projection
9. External mappings
10. Audit/history

Identity:
- company context;
- Product Code;
- name/description;
- GOODS/SERVICE;
- ACTIVE/INACTIVE.

Capabilities:
- SELLABLE
- PURCHASABLE
- STOCKABLE

SERVICE + STOCKABLE validation is rejected.

## 4. Variant UX

Variant grid:
- Variant/SKU Code;
- name/differentiators;
- active state;
- UOM/barcode count;
- tracking strategy if variant-scoped.

If Product has no variants, UI does not force an artificial visible "default variant".

If variants exist, F2/scanning must return the concrete selectable trade identity without hiding which variant is chosen.

## 5. UOM UX

Show:
- Base UOM clearly labelled;
- alternate UOM;
- conversion as human-readable equation, e.g. `1 KOLİ = 12 ADET`;
- state;
- future-use warning when changing an established conversion.

Do not show a conversion factor without its direction.

Posted document views show their snapshot conversion rather than silently using current master.

## 6. Barcode UX

Grid:
- code;
- type/namespace;
- Product/Variant;
- UOM/package;
- ACTIVE/INACTIVE;
- external verification metadata where applicable.

Create/edit:
- immediate ambiguity/duplicate validation;
- scanner input supported;
- GS1 GTIN labelled as external trade-item identifier, not parsed into Product semantics.

A barcode search result must identify:
- Product;
- Variant if any;
- UOM/package conversion;
- inactive/blocked state.

## 7. Category UX

- normalized category picker/tree/list;
- optional primary category;
- multiple classifications visible where configured;
- cycle/error state handled explicitly.

No free-text comma-separated category IDs.

## 8. Warehouse / Location UX

Warehouse list/detail:
- company;
- code/name;
- ACTIVE/INACTIVE;
- location count;
- derived on-hand summary.

Location hierarchy:
- tree/breadcrumb;
- stock-bearing/selectable flag;
- state;
- current derived quantity summary.

Do not make aggregate location editable stock truth.

## 9. Inventory status / projection UX

Stock panel must distinguish:

- On Hand
- Available Physical
- Reserved
- Available to Reserve
- Quarantine/Hold
- Rework
- Damaged
- Transit

Labels must make clear:
- Reservation is commitment, not physical status.
- quantities are projections from ledger/reservations.

No "set stock = X" action.

Adjustment/count belongs later Warehouse workflow and must post ledger effect.

## 10. Lot/Serial UX

Lot view:
- Product/Variant;
- lot;
- manufacture/expiry where applicable;
- warehouse/location/status;
- derived quantity;
- expired indicator.

Serial view:
- Product/Variant;
- serial;
- current derived warehouse/location/status;
- lot where applicable;
- movement history.

Scan flow validates:
- Product/Variant;
- barcode;
- required lot/serial;
- location;
- status;
- quantity.

Expired lot is visibly not normal-pick eligible.

## 11. Inactive master behavior

Inactive Product/Variant/UOM/barcode:
- visually explicit;
- excluded from normal new-document lookup by default;
- can remain visible in historical/admin search;
- existing physical stock resolution workflows remain possible for authorized Warehouse users.

Deactivation confirmation warns:
- current on-hand;
- reservations;
- open physical processes when projections exist.

## 12. Historical snapshot UX

Posted Sales/Purchasing/Inventory document detail displays historical snapshot fields separately from current master navigation.

If current Product Code/name/UOM differs:
- do not silently replace historical text;
- optional "Current master" navigation is clearly separate.

## 13. Keyboard/mobile/error

Keyboard:
- F2 lookup;
- predictable Tab/Enter/Escape;
- scan input focus support;
- accessible visible focus.

Mobile warehouse:
- scan-first;
- large targets;
- Product/Variant/lot/serial/location/status confirmation;
- offline/sync state visible where later implemented.

Errors distinguish:
- duplicate code/barcode;
- inactive identity;
- stale edit;
- conversion conflict;
- lot/serial mismatch;
- cross-company access;
- concurrency/availability conflict.

Recoverable form input should not be discarded.
