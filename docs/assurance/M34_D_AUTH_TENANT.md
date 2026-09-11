# M34 D — Auth, RBAC and tenant isolation assurance

Slice D adds a focused behavioral suite to the existing `browser-smoke` self-hosted runner after an isolated PostgreSQL schema is prepared.

The suite executes existing regression tests for authentication, authorization, active company and branch context, B2B authentication/portal boundaries, account B2B policy, product authorization, and treasury statement authority. Missing expected test files fail closed.

On success it writes `storage/app/assurance/auth-tenant-report.json` with the exact test-file set and tested trust-boundary categories.

No additional CI job is introduced; the real Foundation gates remain exactly `security`, `quality`, and `browser-smoke`.
