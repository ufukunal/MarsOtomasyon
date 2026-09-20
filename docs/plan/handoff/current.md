# Current Handoff

## Repository
- Repository: `ufukunal/MarsOtomasyon`
- Allowed branch: `main`
- Current HEAD: **verify from Git at session start**
- Last completed planning commit: `3a36a50a2978a7307ff395f79f00497ccc2ed59b`

## Current phase
P2 — Core Commercial Workflow Planning

## Last completed task
`PLAN-001 — Planning backbone and Foundation plan`

PLAN-001 established:
- project-state
- active-task mechanism
- roadmap
- planning standard
- task tracking
- decision record area
- Foundation plan
- initial DB dictionary/principles

## Verified test infrastructure
- Dedicated MarsOtomasyon/pre-accounting test server exists at `mars-prod.taila20365.ts.net`.
- Self-hosted/local runner runs on a separate server/VM: `mars-ci`.
- Runner VM and test server are NOT the same machine.
- PostgreSQL Docker is on the test server.
- Test server IP and remaining unverified topology details remain UNKNOWN unless explicitly checked.
- Repository visibility is verified as private.
- Source file: `docs/plan/01-foundation/test-environment.md`

## Test credential policy
- Project owner explicitly permits TEST-ONLY credentials to be stored in this private repository.
- Dedicated path: `config/test/test-server.credentials.env`
- Production credentials must never be stored there.
- Actual credential values are still pending owner input and must not be invented.
- Credential values must not be repeated in logs or assistant final reports.

## Active task
`PLAN-002 — Detail Sales domain workflows before database schema`

Target:
`docs/plan/05-satis/`

Required chain:
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
- purpose
- states
- source/target links
- DOC/RES/STOCK/ACCOUNT/CASH-BANK/COST effects
- line quantities
- partial operations
- approval
- cancel/reversal
- audit
- outbox/integration effects

## Existing locked rules
- Quote has no stock/accounting effect.
- Sales Order may reserve stock but does not physically issue it.
- Dispatch is the normal physical stock-out point.
- Invoice creates the financial receivable.
- If invoice is sourced from dispatch, stock must not be posted a second time.
- Collection is a separate financial settlement event.
- Return physical disposition and financial credit/refund are separate concerns.
- Posted history is reversed, not silently overwritten.

These rules can be refined by explicit project decisions but must not be silently contradicted.

## Do not do during PLAN-002
- do not create SQL schema
- do not create application code
- do not invent unresolved commercial policy
- do not start Purchase workflow
- do not run heavy tests
- do not create branch/PR

## Heavy tests
Sales heavy scenarios belong to Full Test Day backlog only.
