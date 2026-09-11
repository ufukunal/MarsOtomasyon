# M34 F — Database lifecycle, integrity and concurrency assurance

Slice F adds focused PostgreSQL lifecycle and concurrency regression coverage to the existing `browser-smoke` self-hosted runner.

After `migrate:fresh`, the gate proves migration status/idempotency and executes existing tests for PostgreSQL foundation behavior, query-plan regressions, idempotency transaction boundaries, outbox leasing, legacy migration control, two-phase sales-order reservations, and stock reservations. Missing expected tests fail closed.

On success it writes `storage/app/assurance/database-lifecycle-report.json`.

No standalone PostgreSQL CI job is reintroduced. The real Foundation gates remain exactly `security`, `quality`, and `browser-smoke`.
