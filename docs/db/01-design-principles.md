# PostgreSQL Logical Design Principles

Status: FROZEN — PLAN-010 logical model.

## 1. Workflow before schema
Every authoritative entity must trace to an accepted frozen workflow. Screen layout alone is never a schema requirement.

## 2. Bounded-context ownership
Party, Product/Inventory, Sales, Purchasing, Warehouse, Finance, Checks/Notes, Returns and Foundation own distinct logical records. Shared technical primitives do not erase domain ownership.

Avoid:
- one universal nullable document table;
- one universal ledger table;
- duplicate stock/balance authorities across modules.

## 3. Normalization
Default OLTP target is 3NF.

Intentional denormalization is limited to:
- immutable historical snapshots;
- projections/read models;
- later measured performance optimizations.

No comma-separated IDs or JSONB used to avoid normal relational links.

## 4. Identity
Planning default:
- internal identity: BIGINT-style surrogate intent where useful;
- public identity: UUID intent for externally addressable aggregate/entity;
- business/document number: separate scoped natural/business identity;
- provider ID: separate External Mapping;
- idempotency identity: separate logical-operation identity.

Not every leaf/junction requires a public UUID.

## 5. Numeric values
Money, quantity, exchange rate and cost use decimal/NUMERIC semantics.
Exact precision/scale is a physical-schema decision driven by frozen business ranges and currency/UOM needs.
Floating-point accounting truth is forbidden.

## 6. Authoritative truth
Authoritative:
- domain master/document records;
- Inventory Ledger;
- Reservation records;
- Account/Cash/Bank ledgers;
- Finance valuation/cost records;
- instrument movement history;
- return authorization/source lineage;
- Foundation outbox/idempotency/approval/audit evidence where applicable.

Not authoritative:
- mutable Product/Warehouse stock totals;
- mutable Party/Cash/Bank balance fields;
- Invoice paid/open fields;
- aging/open-item allocation;
- dashboard/read projections;
- Valkey/cache.

## 7. Posted history
Posted physical/financial/instrument effects are append/reversal oriented.
Silent update/delete of posted history is forbidden.

## 8. Historical snapshots
Live master normalization and historical correctness coexist.
Frozen documents preserve required Party, Product/UOM, tax, commercial and FX values.
Snapshots do not become alternate live master records.

## 9. Scope
Company is explicit on every company-authoritative aggregate and ledger effect.
Branch/Warehouse are stored only when required by ownership/invariant.
Cross-company transactional relations are forbidden.

## 10. Source-target lineage
Quantity/value-bearing workflow relations are normalized explicit records.
They reference exact source/target line/version/movement where required.
Cumulative eligibility is computed from authoritative sources + net active links/effects.

## 11. Constraints
Future physical schema must use relational guarantees where expressible:
- PK/FK;
- unique;
- not-null;
- check;
- scoped business uniqueness;
- reversal/idempotency uniqueness;
- valid same-company ownership.

Cross-row cumulative caps and availability checks may require transactional locking/version strategies in addition to constraints.

## 12. Concurrency
Correctness is durable in PostgreSQL, not Valkey.
Risk areas define locking/version/unique/idempotency requirements before implementation:
Reservation, Dispatch, Receipt/Invoice, returns, serial movement, finance posting, reconciliation, instrument settlement and reversal.

## 13. Transaction boundaries
One business POST that creates multiple authoritative effects must commit atomically in PostgreSQL.
Examples:
- Collection → Account + Cash/Bank;
- Payment → Account + Cash/Bank;
- Dispatch → Dispatch posting + Inventory effect + Reservation consume/release + valuation effect references;
- Goods Receipt → receipt posting + Inventory IN + valuation input/reference;
- refund → Account + Cash/Bank.
External side effects occur through outbox after durable commit.

## 14. Projections
Stock, balances, aging, risk, progress, maturity and dashboards are rebuildable.
A projection failure cannot change authoritative ledger/document truth.

## 15. Indexing
Logical index intent follows actual access patterns and constraints.
Do not add every FK/index combination by habit.

## 16. Migration
Physical schema changes later use migrations only.
Every migration reviews lock duration, backfill, destructive change, forward-fix/rollback and deployment ordering.

## 17. Technology decision gates
PLAN-010 does not select ORM, physical schema naming, exact NUMERIC precision/scale or PostgreSQL locking syntax unless required by logical correctness.
