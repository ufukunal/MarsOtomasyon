# Foundation Plan

## 1. Architecture target

```
Mars.sln
src/
├── Mars.Api
├── Mars.Application
├── Mars.Domain
├── Mars.Infrastructure
├── Mars.Worker
├── Mars.Contracts
└── Mars.Device
```

This is a planning target, not proof that these paths already exist.

Domain-oriented modules live behind explicit boundaries. Foundation must not become a God module.

## 2. Module boundary policy
A module owns:
- its domain entities/value objects
- its business invariants
- its commands/queries
- its persistence mapping
- its application services
- its internal events

Cross-module access must use an explicit contract, not direct internal table/entity coupling where avoidable.

## 3. Command contract
Every state-changing command should define:
- actor
- company/branch scope
- target aggregate/document
- expected state/version where concurrency matters
- idempotency key where duplicate submission is possible
- validation
- transaction boundary
- emitted domain/integration events
- audit payload

## 4. Query contract
Queries:
- do not mutate business state
- may use read projections
- support paging/filter/sort where data can grow
- respect tenant/company/branch scope
- do not expose internal identifiers unnecessarily

## 5. Transaction policy
- one business action has one explicit transaction boundary
- authoritative DB changes commit atomically
- external provider calls are not part of DB atomicity
- external side effects use transactional outbox when delivery reliability matters
- distributed transactions are not a default solution

## 6. Outbox
Required fields conceptually:
- id
- public/event id
- event type
- aggregate/module reference
- payload/version
- created_at
- available_at
- processed_at
- attempt_count
- last_error

Exact schema belongs in DB planning after requirements are frozen.

## 7. Idempotency
Use where duplicate requests can cause duplicate business effects:
- external orders
- payment callbacks
- webhooks
- document posting
- worker event consumption
- mobile offline retry

Idempotency must have a durable DB guarantee, not only in-memory cache.

## 8. Audit
Audit should capture:
- actor
- timestamp
- action
- entity/document reference
- company/branch
- reason where required
- correlation id
- before/after only where appropriate and safe

Audit is not a replacement for ledger history.

## 9. Identity
Planning default:
- internal database key: BIGINT
- public API identity: UUID
- external provider identity: separate mapping

This must be reviewed per entity before schema implementation.

## 10. Authorization
Foundation provides permission primitives.
Domain modules define permission names.

Example convention:
`sales.invoice.create`
`sales.invoice.post`
`inventory.count.approve`

Authorization is enforced server-side. UI hiding is not security.

## 11. Company / branch / warehouse scope
Scope must be explicit per entity and command. Do not add all scope columns blindly to every table.

Foundation must provide:
- current actor context
- current company context
- optional branch context
- scope validation helpers

Warehouse scope remains Inventory/Warehouse domain behavior.

## 12. Numbering
Document numbering must support:
- document type
- company
- branch where required
- series
- period where required
- concurrency-safe next number
- immutable posted number
- human-visible number separate from internal key

Exact format remains module/business decision.

## 13. Error model
Separate:
- validation
- authorization
- not found
- conflict/state transition
- concurrency
- business rule
- infrastructure/provider failure

API maps these deterministically to responses.

## 14. Files
Foundation may provide file storage abstraction:
- metadata
- storage key
- mime/size
- owner/module/entity reference
- authorization hooks

Domain meaning remains module-owned.

## 15. Notifications
Foundation/communications integration uses:
Domain Event → Outbox → Worker → Communication Orchestrator → Provider Adapter.

Business modules do not call SMS/mail/WhatsApp providers directly.

## 16. API conventions
- base: `/api/v1`
- explicit request/response contracts
- pagination/filter rules
- deterministic validation
- public UUIDs
- correlation id
- idempotency key where needed
- OpenAPI

## 17. Client platform abstraction
Shared client core should isolate:
- camera
- barcode scanner
- printer
- file picker
- notifications
- secure credential storage where applicable

Web, Desktop and Mobile shells provide adapters.

## 18. Frontend foundation
- own Mars component system
- no React/Vue/Angular/Bootstrap/Tailwind/jQuery
- TypeScript + ES Modules + Vite
- `border-radius: 0`
- standard keyboard interactions
- accessible focus
- reusable grid/lookup/dialog/tabs components

## 19. Cache
Valkey can provide:
- cache
- session
- distributed locks where justified
- transient coordination

Rules:
- no authoritative balance
- no authoritative stock
- system remains recoverable if cache is flushed

## 20. Observability
Shared context:
- correlation id
- structured logs
- service/module
- actor/company where safe
- latency
- errors
- worker/outbox age

Secrets and sensitive PII must be masked.

## 21. Configuration and secrets
- environment-specific config
- secrets outside source control
- typed options/config validation
- fail fast on missing critical secret/config
- provider credentials isolated by adapter/account

## 22. Migration policy
- schema only through migrations
- destructive changes explicitly reviewed
- large backfill/lock risk assessed
- deploy order documented
- migration failure must not result in false healthy status

## 23. Dependency policy
Before adding a package:
- existing capability checked
- need justified
- license checked
- maintenance state checked
- security checked
- lock-in assessed

## 24. UNKNOWN / decisions still required
These are not to be invented during implementation:
- exact auth/identity provider and token/session implementation
- exact Desktop shell technology
- exact Mobile shell technology
- exact object/file storage implementation
- exact job scheduling library if any
- exact OpenAPI tooling
- exact logging/metrics stack

These must be resolved when implementation reaches them or when an ADR is deliberately created.

## 25. Next dependency
After Foundation plan acceptance, Sales workflow planning starts before DB schema.
