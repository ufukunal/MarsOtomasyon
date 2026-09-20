# PostgreSQL Design Principles

## 1. Workflow before schema
Do not design a transactional table merely from a screen. Start from accepted workflow, state and effect contracts.

## 2. Normalization
Default OLTP target: 3NF.

Allowed intentional denormalization:
- historical snapshots
- read projections
- measured performance optimizations

## 3. IDs
Planning default:
- internal PK: BIGINT
- public ID: UUID
- provider IDs: separate mapping

Do not treat this as permission to add both blindly to every table; review per entity.

## 4. Numeric values
- money: NUMERIC/decimal
- quantity: NUMERIC/decimal
- exchange rates may need higher scale
- float/double is forbidden for accounting quantities

Precision/scale is chosen from business requirements, not guesswork.

## 5. Source of truth
Do not create authoritative mutable:
- customer balance columns
- supplier balance columns
- product stock columns

Truth derives from posted ledgers/documents. Projections may cache calculated state.

## 6. Posted history
Posted financial/inventory history is not silently updated or deleted.
Corrections use reversal/compensating entries according to workflow.

## 7. Constraints
Critical invariants should be protected with DB constraints where relationally expressible:
- PK
- FK
- unique
- check
- not null

Application validation does not replace DB integrity.

## 8. Multi-company scope
Scope is explicit and minimal:
- tenant if required
- company
- branch where required
- warehouse where required

Do not scatter all scope fields onto every table without ownership analysis.

## 9. Concurrency
Where oversell/double-post/duplicate callback is possible, design a durable concurrency/idempotency guarantee.

## 10. Snapshots
Freeze historical values needed for correctness. Master-data normalization does not override legal/operational historical accuracy.

## 11. JSONB
JSONB is permitted for genuinely flexible provider metadata or non-relational payloads. It is not an escape hatch for normal relationships.

## 12. Indexes
Indexes derive from access patterns and constraints. Do not add broad indexes by habit.

## 13. Migrations
All schema changes use migrations.
Every migration must consider:
- lock risk
- data backfill
- destructive change
- deployment order
- rollback/forward-fix strategy

## 14. Next step
Detailed entities and schema are deferred until core Sales/Purchasing/Warehouse/Finance workflows are accepted.
