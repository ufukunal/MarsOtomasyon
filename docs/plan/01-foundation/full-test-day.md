# Foundation — Full Test Day Backlog

Do not run these during ordinary planning/development unless Full Test Day is explicitly started.

Planned heavy verification categories:
- full authorization matrix
- tenant/company/branch isolation regression
- outbox crash/retry/duplicate delivery scenarios
- idempotency under concurrency
- migration upgrade/rollback rehearsal
- backup/restore validation
- sustained worker failure/recovery
- API load/performance baseline
- secret/log leakage review
- browser/client cross-platform regression
