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
- P4 Foundation implementation: COMPLETED
- FW-IMP-001 through FW-IMP-008: COMPLETED

## Current phase
P5 — Core application implementation

Status:
- IMPLEMENTATION READY

## Active work package
- PARTY-IMP-001 — Create Party Core Identity
- status: READY / NOT STARTED
- canonical readiness: `docs/plan/03-cariler/p5-first-slice-readiness.md`
- authorization ADR: `docs/plan/decisions/ADR-0005-mars-erp-permission-authority.md`

## Blocker-resolution outcome

PARTY-BLK-001 — RESOLVED:
- Mars-owned PostgreSQL Permission Grant authority;
- initial effective grain ActorId + CompanyId + PermissionCode;
- `party.create` evaluated server-side;
- Identity/OpenIddict authentication/token claims are not ERP permission authority.

PARTY-BLK-002 — RESOLVED for first slice:
- Party Code is required caller input;
- no auto-number allocator or exact format is invented;
- Settings/Numbering remains owner of future allocation/series policy;
- exact stored Party Code is unique within CompanyId;
- leading/trailing whitespace is rejected; no case-folding rule is invented.

PARTY-BLK-003 — RESOLVED by explicit deferral:
- soft/fuzzy duplicate candidate detection is outside PARTY-IMP-001;
- no fuzzy algorithm/threshold is invented;
- full PLAN-003 Create Party parity cannot be claimed until a later Party slice adds accepted duplicate review behavior.

PARTY-BLK-004 — RESOLVED:
- Party stores trusted required CompanyId UUID;
- request cannot supply CompanyId;
- no physical Company FK/table is added in PARTY-IMP-001.

## PARTY-IMP-001 implementation boundary

Include:
- Foundation Permission Grant persistence/evaluator sufficient for `party.create`;
- Party core aggregate/persistence only;
- additive EF migration;
- protected `POST /api/v1/parties`;
- `/parties/new` Mars.Web identity-only form;
- audit;
- durable idempotency;
- targeted tests;
- TEST deployment/smoke when deployable.

Exclude:
- roles;
- tax identities;
- contacts;
- addresses;
- external mappings;
- merge;
- soft duplicate warnings;
- Settings/Numbering allocator;
- permission administration UI/role-group model;
- other ERP modules;
- production deployment.

## Planning progress
- Master section-8 sequence: 9 / 30 = 30.0%
- P2 core commercial: 8 / 8 = 100.0%

## Full Test Day pending
- Party Code duplicate concurrency under real PostgreSQL race
- future tax-identity collision concurrency
- stale Party edits
- cross-company IDOR matrix
- broad permission matrix
- browser Party lifecycle E2E
- high-volume fuzzy duplicate search
- downstream snapshot immutability
- security/privacy regression
