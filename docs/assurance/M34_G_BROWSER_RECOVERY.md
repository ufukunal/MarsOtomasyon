# M34 G — Browser, recovery and failure assurance

Slice G keeps real browser coverage in the existing `browser-smoke` self-hosted runner and adds focused recovery/failure regressions before the browser suite.

The focused suite executes readiness, recovery-mode, recovery-safety, offsite-backup readiness, Update Center stability, and production-provider kill-switch tests. Missing expected test files fail closed. The existing Playwright-backed browser suite then exercises the real UI flows on the same isolated PostgreSQL database.

On success the focused suite writes `storage/app/assurance/recovery-failure-report.json`.

No additional Foundation job is introduced; the real gates remain exactly `security`, `quality`, and `browser-smoke`.
