# Current Handoff

## Repository

- Repository: ufukunal/MarsOtomasyon
- Allowed branch: main
- Current HEAD: verify from Git at session start
- Master plan: docs/plan/master-project-plan.md
- Session protocol: docs/ai/session-execution-protocol.md

## Current phase

P2 — Core Commercial Workflow Planning

## Completed predecessor

PLAN-003 — Party / Customer / Supplier model

Status: COMPLETED / FROZEN

Primary completion evidence:
- `52628746f484919f370feae5cf7be3ab6c87d8ef` — PLAN-003 acceptance criteria completed after Party identity/role/lifecycle contracts were frozen.

Party planning contracts:
- docs/plan/03-cariler/README.md
- docs/plan/03-cariler/plan.md
- docs/plan/03-cariler/workflows.md
- docs/plan/03-cariler/forms.md
- docs/plan/03-cariler/data-contract.md
- docs/plan/03-cariler/permissions.md
- docs/plan/03-cariler/integrations.md
- docs/plan/03-cariler/reports.md
- docs/plan/03-cariler/acceptance-criteria.md
- docs/plan/03-cariler/full-test-day.md

No SQL schema/migration, application code, deployment or heavy tests were created/run by PLAN-003.

## Frozen Party decisions

- Party is the company-scoped authoritative live counterparty master.
- Party kind is PERSON or ORGANIZATION.
- CUSTOMER and SUPPLIER are roles and may coexist on one Party.
- canonical Party Code is company-scoped and role-neutral; numbering format is Settings/Numbering-owned.
- legal identity vs display/trade identity is explicit.
- contacts, communication points, addresses and tax identities are normalized Party-owned master data.
- Turkish VKN/TCKN structural schemes are represented as 10/11-digit identity types; downstream legal-document validation follows current official rules.
- live master changes never rewrite posted/frozen document snapshots.
- Party states are ACTIVE / INACTIVE / MERGED; role state is independently ACTIVE / INACTIVE.
- fuzzy duplicate matching is warning-only; deterministic identity collision is conflict.
- merge is logical/audited with source→survivor lineage and no historical hard delete.
- customer/supplier current balance, credit/risk/hold and settlement/netting remain Finance-owned.
- no automatic customer/supplier balance netting is introduced.
- cross-company Party sharing is forbidden in PLAN-003; each company owns its Party master.
- exact credit formula, netting, numbering string format, e-document enrollment/checksum and payment-term precedence are delegated to their owning later plans and are not Party-core blockers.

## Preserved Sales dependencies

- Sales Quote/Order/Invoice use current eligible Customer role but freeze historical Party identity/address/tax snapshots.
- customer current balance remains Finance-ledger-derived.
- Sales Collection remains balance-only and is not Invoice allocation.
- Party deactivation/merge does not mutate posted Sales history.

## Next safe work package

PLAN-004 — Product / Inventory master model

Target:
`docs/plan/04-urun-stok/`

PLAN-004 is READY but content work has not started in this handoff.

Before work:
- verify real main HEAD;
- read governance/master/state/handoff;
- read frozen Sales and Party contracts;
- inspect current 04-urun-stok files;
- route skills through skill-router;
- produce CONTEXT RECEIPT.

Do not jump to Purchasing, Warehouse implementation, logical SQL schema, application code or Full Test Day.
