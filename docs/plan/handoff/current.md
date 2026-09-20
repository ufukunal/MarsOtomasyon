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
- Test server Tailscale IP is verified as `100.88.237.117`; remaining unverified topology details stay UNKNOWN.
- Repository visibility is verified as private.
- Source file: `docs/plan/01-foundation/test-environment.md`

## Verified SSH diagnostic
- Runner machine: `mars-ci`
- Runner user: `actions`
- Tailscale/MagicDNS: PASS
- Test server Tailscale IP: `100.88.237.117`
- TCP/22: PASS
- SSH authentication: PASS
- Remote user: `ufuk`
- Remote hostname: `mars-prod`
- Remote kernel: `6.8.0-139-generic`
- Docker: `29.8.0`
- PostgreSQL container: `marsotomasyon-postgres-1`, image `postgres:18-bookworm`, running/healthy
- Valkey container: `marsotomasyon-valkey-1`, image `valkey/valkey:8-alpine`, running/healthy
- `/opt/marsotomasyon`: present
- These are remote test-server facts, not Runner VM facts.

## Test credential policy
- Project owner explicitly permits TEST-ONLY credentials to be stored in this private repository.
- Dedicated path: `config/test/test-server.md`
- Production credentials must never be stored there.
- SSH test-server credentials are configured in the dedicated Markdown file and were verified by successful remote login; PostgreSQL credentials remain UNKNOWN.
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
