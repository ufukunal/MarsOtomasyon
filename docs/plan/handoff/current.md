# Current Handoff

## Repository
- Repository: `ufukunal/MarsOtomasyon`
- Allowed branch: `main`
- Current HEAD: **verify from Git at session start**
- Master plan: `docs/plan/master-project-plan.md`
- Session protocol: `docs/ai/session-execution-protocol.md`

## Current phase
P2 — Core Commercial Workflow Planning

## Last completed work package
`FRAMEWORK-001 — Foundation / Framework contract`

This planning work package established:
- end-to-end master project plan
- Foundation/framework architecture contract
- solution/project topology target
- modular-monolith dependency rules
- command/query/transaction/error/context contracts
- idempotency/audit/outbox/worker contracts
- API and migration framework boundaries
- PostgreSQL/Valkey authority boundaries
- Mars.Web/Mars.UI architecture
- V38 preservation vs technical-debt rules
- Docker/test deployment model
- role-driven SESSION REPORT and NEXT PROMPT protocol

Important: this is planning evidence only. No application framework code has been implemented yet.

## Canonical UI/product reference
`docs/reference/ui/marsotomasyon_ui_v38_cari_bakiye_sadelestirildi.html`

Rule:
- preserve validated product/UX behavior and visual character,
- do not use the V38 patch chain as production architecture,
- classify module screens as KEEP / ADAPT / MERGE / REMOVE / BLOCKED during module planning.

## Verified test infrastructure
- Dedicated MarsOtomasyon/pre-accounting test server exists at `mars-prod.taila20365.ts.net`.
- Self-hosted runner executes on separate host `mars-ci`.
- Runner and test server are different machines.
- PostgreSQL 18 Docker container is running/healthy on the test server.
- Valkey 8 Docker container is running/healthy on the test server.
- SSH path from runner to test server has been verified.
- `mars_master` PostgreSQL administrative role exists and login was verified.
- `mars_app` restricted application role exists and login was verified.
- `mars_app` is not SUPERUSER/CREATEDB/CREATEROLE/REPLICATION.
- Credential values must never be repeated in final reports or workflow logs.

## Framework decision gates still UNKNOWN
These are not to be guessed:
- exact .NET SDK/runtime version
- ORM/data-access library
- auth/identity provider
- OpenAPI tooling
- logging/metrics stack
- object/file storage backend
- Desktop shell technology
- Mobile shell technology
- optional scheduler library
- production secret store
- production reverse proxy/tunnel details

These do not block Sales workflow planning unless a Sales decision directly depends on them.

## Active task
`PLAN-002 — Detail Sales domain workflows before database schema`

Target:
`docs/plan/05-satis/`

Required business chain:

```
Quote
→ Sales Order
→ Reservation
→ Dispatch
→ Invoice
→ Collection link
→ Return link
```

For each document/action define:
- business purpose and process owner
- states / allowed and forbidden transitions
- source/target document links
- DOC effect
- RES effect
- STOCK effect
- ACCOUNT effect
- CASH/BANK effect
- COST effect
- ordered/reserved/shipped/invoiced/returned/remaining quantities
- partial operation behavior
- approval
- cancellation vs reversal
- audit
- outbox/integration effect
- UI behavior at planning level
- KPI/control impact where relevant

## Locked Sales rules already present in repository
- Quote has no stock/accounting effect.
- Sales Order may reserve stock but is not physical stock-out.
- Dispatch is the normal physical stock-out point.
- Invoice creates financial receivable.
- Dispatch-sourced invoice must not post stock a second time.
- Collection is a separate financial settlement event.
- Return physical disposition and financial credit/refund are separate concerns.
- Posted history is corrected by reversal, not silent overwrite.

## Required roles for PLAN-002
Primary:
- erp-domain-specialist — commercial workflow/effect/state ownership
- accounting-finance-specialist — financial recognition/settlement/reversal ownership

Reviewers:
- mba-business-manager — process owner/business value/control review
- warehouse-operations-shipping-specialist — reservation/dispatch/return physical-flow review
- database-architect — future data-model/invariant feasibility review without creating schema
- software-architect — module/transaction/contract boundary review
- software-test-engineer — acceptance/invariant/edge-case review
- ux-ui-specialist — partial/state/action visibility review

## Do not do during PLAN-002
- do not create application code
- do not create SQL schema/migrations
- do not start Purchasing
- do not invent unresolved commercial policy
- do not redesign V38 globally
- do not run heavy tests
- do not create branch/PR or force push
- do not expose credentials

## Completion protocol
Every session must follow:
- `docs/ai/session-execution-protocol.md`
- produce CONTEXT RECEIPT before changes
- produce SESSION REPORT after changes
- update state/handoff if status changes
- end with standalone role-driven NEXT PROMPT generated from final verified HEAD
