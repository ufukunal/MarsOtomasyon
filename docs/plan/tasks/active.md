# Active Tasks

## FW-IMP-004 — audit/idempotency/outbox foundations
**Status:** READY — IMPLEMENTATION NOT STARTED

Predecessor:
- FW-IMP-003 — COMPLETED
- build/test/migration evidence: GitHub Actions run `35708370550`

Accepted persistence baseline:
- EF Core 10 + Npgsql is the default PostgreSQL persistence/migration architecture.
- targeted raw Npgsql/SQL is exception-only.
- exact committed versions are recorded in FW-IMP-003 evidence.

Scope from Foundation framework plan:
- audit persistence primitive;
- durable PostgreSQL idempotency primitive;
- transactional outbox persistence primitive;
- application interfaces;
- minimum worker/outbox processing skeleton;
- targeted tests and migration checks.

Do not expand into:
- domain module schema or business posting rules;
- auth provider;
- OpenAPI tooling;
- Mars.Web/Mars.UI;
- deployment.

Exact planning progress:
- master: 9 / 30 = 30.0%
- P2: 8 / 8 = 100.0%

Heavy tests remain Full Test Day only.
