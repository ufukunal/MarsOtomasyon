# Current Handoff

## Repository
- Repository: `ufukunal/MarsOtomasyon`
- Allowed branch: `main`
- Current HEAD: **verify from Git at session start**
- Baseline used to start this planning task: `f15a89bf94280f0990291037037436d3dd283bbe`

## Current phase
P0 — Planning and Foundation Definition

## Current task
`PLAN-001 — Establish project planning backbone and Foundation plan`

## Completed before this task
- main-only Git workflow
- AI command protocol
- 16 detailed AI skills
- skill router
- mandatory autocomplete/NEXT PROMPT protocol
- 29 module planning folders

## Work being established
- structured project state
- active task
- roadmap
- planning standard
- task lists
- decisions directory
- Foundation plan
- initial DB planning principles

## Rules that must not be lost
- only `main`
- no branch
- no PR
- no force push
- PostgreSQL source-of-truth
- Valkey is non-authoritative
- modular monolith
- ledger-based stock/finance truth
- reversal instead of silent posted-record mutation
- no heavy test suite during normal development
- no undocumented business-rule assumptions

## Next exact action
After PLAN-001 is verified, start `PLAN-002`:
Detail the Sales workflow in `docs/plan/05-satis/` before designing its database schema.

Start with:
- Quote
- Sales Order
- Reservation
- Dispatch
- Invoice
- Collection link
- Return link

For each, define:
- states
- form/effect matrix
- line quantity tracking
- partial operation
- source/target documents
- cancel/reversal
- stock/accounting effects

## Heavy tests
None should run during planning.
