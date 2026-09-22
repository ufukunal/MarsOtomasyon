# Active Tasks

## FW-IMP-003 — persistence/migration baseline
**Status:** READY — IMPLEMENTATION NOT STARTED

Predecessor:
- FW-IMP-002 — COMPLETED
- build/test evidence: GitHub Actions run `35704843486`

Accepted persistence decision:
- EF Core 10 + Npgsql is the default PostgreSQL persistence/migration architecture.
- targeted raw Npgsql/SQL is exception-only.

Scope from Foundation framework plan:
- create PostgreSQL connection/persistence baseline;
- establish migration mechanism;
- preserve explicit transaction and migration/runtime boundaries;
- no domain schema beyond Foundation-owned baseline structures;
- targeted persistence/migration contract tests.

Do not expand into:
- Sales/Party/Product/Inventory/Purchasing/Warehouse/Finance schema or mappings;
- audit/idempotency/outbox implementation owned by FW-IMP-004;
- auth provider;
- OpenAPI tooling;
- Mars.Web/Mars.UI;
- deployment.

Exact planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%

Heavy tests remain Full Test Day only.
