# Product / Inventory Master Permissions and Scope

Status: FROZEN planning-level permission contract.

## 1. Principles

- authorization is server-side;
- every mutation validates company and relevant Warehouse scope;
- master-data read does not imply physical inventory posting rights;
- Product editing cannot directly modify stock quantity;
- sensitive/high-risk capability, tracking and warehouse changes use distinct permissions;
- UI hiding is not security.

## 2. Proposed permission namespace

Product:
- product.read
- product.create
- product.edit
- product.deactivate
- product.reactivate
- product.export

Variant:
- product.variant.read
- product.variant.manage

UOM:
- product.uom.read
- product.uom.manage
- product.uom.change_base

Barcode:
- product.barcode.read
- product.barcode.manage

Category:
- product.category.read
- product.category.manage

Warehouse master:
- inventory.warehouse.read
- inventory.warehouse.manage
- inventory.warehouse.deactivate

Location master:
- inventory.location.read
- inventory.location.manage
- inventory.location.deactivate

Lot / Serial:
- inventory.trace.read
- inventory.lot.manage_metadata
- inventory.serial.manage_metadata

Inventory quantity projections:
- inventory.stock.read

Reservation/physical posting permissions are owned by Sales/Inventory/Warehouse workflows and are not implied by Product master permissions.

## 3. Company / Warehouse scope

Product mutations:
- actor must have Product company access.

Warehouse/Location:
- actor must have company plus accepted Warehouse scope.

Cross-company:
- no mutation/read-use through another company's Product or Warehouse without a future explicit administrative capability.

## 4. Product lifecycle permissions

Create/edit:
- `product.create` / `product.edit`.
- duplicate Product/Variant code/barcode checks.
- audit.

Deactivate:
- `product.deactivate`.
- warning/validation against stock/reservation/open operational use.
- does not zero stock or delete history.

Reactivate:
- `product.reactivate`.
- uniqueness/capability/tracking revalidation.

## 5. High-risk Product changes

Changing any of these after operational use requires explicit permission/check:
- Base UOM;
- STOCKABLE capability;
- lot/serial tracking strategy;
- established Product/Variant code where external/history impact exists.

`product.uom.change_base` does not grant permission to rewrite historical quantities.

A high-risk change is rejected if current stock/reservation/open process makes the transition inconsistent.

## 6. Barcode

`product.barcode.manage`:
- add/deactivate mappings;
- never bypass ambiguity/uniqueness rule.

No permission allows the same active barcode to intentionally resolve ambiguously.

## 7. Warehouse / Location

Warehouse/Location administration:
- master identity/hierarchy/state only.
- cannot set current stock amount.

Deactivation:
- cannot erase physical history.
- unresolved stock/reservation/open-operation checks must pass according to later Warehouse workflow.

## 8. Lot / Serial

Metadata management cannot:
- directly move quantity;
- relocate a serial;
- change current physical status;
- erase posted trace history.

Physical movement requires Inventory/Warehouse posting permissions and ledger workflow.

Correction of posted lot/serial movement uses reversal/compensation, not metadata edit.

## 9. Cost / valuation access

Product master permissions do not grant:
- inventory revaluation;
- cost layer mutation;
- COGS posting;
- financial valuation override.

Those belong Finance/Costing permissions when defined.

Read-only valuation projections require future Finance authorization.

## 10. Export

Product export applies:
- company scope;
- barcode/external mapping permissions where separately restricted;
- no authoritative mutable stock/value fields.

Stock export/query must clearly be a projection and obey Inventory scope.

## 11. Audit

Record for privileged changes:
- actor;
- company/Warehouse context;
- action;
- Product/Variant/UOM/barcode/Warehouse/Location identity;
- prior/new state;
- conversion/tracking/capability change;
- reason where required;
- timestamp/correlation id.

Audit does not replace Inventory Ledger.
