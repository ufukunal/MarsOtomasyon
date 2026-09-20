# Foundation Acceptance Criteria

Foundation planning is accepted when:

- modular-monolith boundary policy is documented
- command/query responsibilities are documented
- transaction policy is explicit
- outbox policy is explicit
- idempotency cases are explicit
- audit vs ledger distinction is explicit
- identity strategy is documented as planning default
- authorization is server-side
- company/branch scope rules are explicit
- numbering requirements are explicit
- error categories are explicit
- communication providers are behind adapters
- API versioning base is defined
- platform/device abstraction is defined
- frontend technology restrictions are defined
- Valkey is explicitly non-authoritative
- observability and secret rules are defined
- migration rules are explicit
- unresolved technology choices are marked UNKNOWN instead of invented
- no application code is claimed to exist merely because it appears in a plan
