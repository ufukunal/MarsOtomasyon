# Current Handoff

## Repository
- Repository: ufukunal/MarsOtomasyon
- Allowed branch: main
- Current HEAD: verify from Git at session start
- Master plan: docs/plan/master-project-plan.md
- Session protocol: docs/ai/session-execution-protocol.md

## Completed
- P2 Core Commercial Workflow Planning: COMPLETED / FROZEN
- P3 Logical Database Model: COMPLETED / FROZEN
- P4 Foundation readiness: COMPLETED
- P4 Foundation implementation: COMPLETED
- FW-IMP-001 through FW-IMP-008: COMPLETED

## Current phase
P5 — Core application implementation

Status:
- BLOCKED — first Parties slice is scoped, implementation has not started.

## Selected first Parties slice

Candidate:
- Create Party Core Identity

Canonical readiness analysis:
- `docs/plan/03-cariler/p5-first-slice-readiness.md`

Why this slice:
- frozen Create Party workflow creates the company-scoped Party before optional CUSTOMER/SUPPLIER role activation;
- only the Party authoritative entity is required;
- no Product/Inventory/Sales/Purchasing/Warehouse/Finance dependency is required;
- action has no Reservation/Stock/Account/Cash-Bank/Cost effect;
- audit is required;
- PartyCreated outbox remains optional without an accepted consumer.

Work-package ID:
- NOT ASSIGNED.

No Party C#/EF migration/API/TypeScript mutation has been made.

## Candidate implementation boundary once unblocked

DB:
- one Parties-owned Party structure;
- BIGINT-style internal key;
- UUID public id;
- trusted CompanyId scope;
- company-scoped unique Party Code;
- PERSON/ORGANIZATION kind;
- legal name;
- optional display name;
- ACTIVE initial state;
- optimistic version.

API:
- candidate `POST /api/v1/parties`;
- authentication required;
- server-side `party.create`;
- no client-authoritative company/branch;
- retry-safe idempotency.

Web:
- candidate `/parties/new`;
- identity-only Mars.UI create form;
- no role/tax/contact/address/Finance fields in first slice.

## Blocking decisions

### PARTY-BLK-001 — Authorization authority
Frozen:
- `party.create` is required;
- server-side authorization is mandatory.

Current source:
- authentication and trusted Actor/Company/Branch context exist;
- no accepted Mars permission evaluator, grant store, role-permission model, permission claim convention or policy-registration implementation exists.

Do not:
- treat authenticated == authorized;
- invent permission tables absent from frozen logical DB;
- invent a permission claim contract and present it as accepted authority.

Required:
- define the Mars-owned permission authority/evaluation contract sufficient for `party.create`.

### PARTY-BLK-002 — Party Code assignment authority
Frozen:
- Party Code is mandatory, company-scoped and role-neutral;
- exact format/series is Settings/Numbering-owned;
- assignment must be concurrency-safe.

Current source does not say:
- user manually supplies Party Code; or
- a runtime numbering service allocates it.

Required:
- define the initial Party Code assignment authority without inventing a format.

### PARTY-BLK-003 — Soft duplicate-warning contract
Frozen Create Party flow requires:
- deterministic duplicate conflict;
- fuzzy candidate warnings;
- no automatic merge;
- authorized keep-separate + reason for soft warnings.

Missing:
- normalization;
- candidate matching algorithm;
- threshold/signal combination;
- candidate query contract;
- explicit permission to defer this warning gate from the first implementation slice.

Required:
- freeze the minimum duplicate-warning contract, or explicitly approve its deferral from the first slice.

### PARTY-BLK-004 — Physical Company reference
Frozen logical model:
- Company 1→N Party.

Current physical Foundation model:
- no Company table exists.

Required:
- explicitly state whether first Party persistence may store trusted CompanyId without a FK until Company persistence exists, or whether Foundation Company persistence is required first.

## Planning progress
- Master section-8 sequence: 9 / 30 = 30.0%
- P2 core commercial: 8 / 8 = 100.0%

Do not change these because of P5 implementation work.

## Full Test Day
Still deferred:
- Party Code allocation concurrency;
- concurrent deterministic Party duplicate creation;
- stale Party edit;
- cross-company IDOR;
- full permission matrix;
- browser Party lifecycle E2E;
- high-volume fuzzy duplicate search;
- historical snapshot integration;
- security/privacy regression.

## Next safe action

Resolve only PARTY-BLK-001 through PARTY-BLK-004.

Do not start Party domain/EF/API/Web implementation and do not assign the first dedicated implementation work-package ID until the acceptance contract is complete.
