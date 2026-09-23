# P5 Parties — Fourth Vertical Slice Readiness

Status: IMPLEMENTED / COMPLETED
Date: 2026-09-23
Phase: P5 — Core application implementation
Module: Parties
Implementation work package: PARTY-IMP-004
Title: Manage Party Role Lifecycle

## Objective

Freeze the smallest coherent Parties vertical slice after PARTY-IMP-003.

Selected slice: **Manage Party Role Lifecycle**

The slice changes one existing CUSTOMER or SUPPLIER Party Role between ACTIVE and INACTIVE while preserving one Party identity, company scope, history and Finance separation. It creates no new role type, Party identity, Finance posting or schema authority.

## Candidate comparison

### Party Role lifecycle — SELECTED
- PLAN-003 freezes Party Role ACTIVE/INACTIVE independently from Party state.
- party.role.manage explicitly covers activate, deactivate and reactivate.
- disabling one role must not disable the other role.
- historical documents remain readable.
- Party Role already contains state + optimistic version.
- PARTY-IMP-002 already provides Party/company lookup, role identity and activation.
- no new authoritative entity or migration is required.
- no legal/provider dependency or fuzzy algorithm.
- no Finance/stock/account/cash/cost posting.
- existing /parties/new can prove activate → deactivate → reactivate.

Reactivation belongs in the same slice because the frozen lifecycle is ACTIVE ↔ INACTIVE, the same permission owns both directions, and PARTY-IMP-002 activation remains first-role creation while this slice owns transitions of an existing role row.

### Soft duplicate candidate/review — deferred
Not implementation-ready because no accepted normalization, similarity algorithm, score or threshold is frozen. Fuzzy signals remain warning/review only and must never auto-merge.

### Contact / Communication / Address — deferred
Contact Person + Communication Point introduce multiple authoritative child types. Address introduces structured fields, purpose/default semantics and downstream snapshot usage. Their migration/UI surface is broader than the existing role-state transition.

### Party deactivate/reactivate — deferred
Party reactivation must rerun current duplicate/legal identity validation and affects all roles/new selection. Its interaction with duplicate/legal readiness is broader than role-only state.

### Merge — deferred
Merge requires survivor/source lineage and conflict review across roles/tax/contact/address/external mappings. Contact/Address/External Mapping are not implemented.

### Tax Identity follow-up — deferred
Read/read_full requires a sensitive disclosure contract; edit/deactivate/verification/non-TR/provider behavior needs separately frozen lifecycle/legal rules.

## Frozen invariants

- Party remains one company-scoped authoritative identity.
- CUSTOMER and SUPPLIER remain roles on that Party.
- Role state is independent from Party state.
- Existing Party Role identity remains Party + RoleType.
- Role states are ACTIVE and INACTIVE only.
- Deactivating CUSTOMER does not deactivate SUPPLIER, and vice versa.
- Role deactivation blocks future role use by default in consuming modules, but this slice does not implement Sales/Purchasing eligibility enforcement.
- Historical documents/history are not deleted or rewritten.
- Reactivation changes the existing role row; it does not insert a second role.
- CompanyId comes only from trusted execution context/owning Party.
- Request cannot choose CompanyId.
- party.role.manage is required for both lifecycle directions.
- Deactivation requires a non-empty reason.
- Reactivation does not invent a mandatory reason.
- No Finance/Account/Cash/Bank/Stock/Reservation/Cost effect.
- No automatic customer/supplier balance netting.
- ADR-0005 remains ERP permission authority.

## Existing physical DB contract

No new table or column is required.

Existing authoritative table: parties.party_roles.

Existing fields used:
- party_id
- role_type
- state
- version
- created_at

Existing constraints remain:
- Party FK with delete RESTRICT
- unique Party + RoleType
- role type CUSTOMER/SUPPLIER representation
- state ACTIVE/INACTIVE representation
- version > 0

Concurrency:
- command requires positive expected role version
- persistence loads Party by trusted CompanyId + Party public UUID
- persistence loads role by Party + RoleType
- expected version mismatch is a concurrency conflict
- EF optimistic concurrency token protects a race after read
- accepted transition increments version by 1
- same-state transition is a conflict, not a silent write

Migration:
- NOT REQUIRED if implementation does not change the EF model
- pending-model verification must prove no migration is required
- no empty/no-op migration is created

## Domain / Application contract

Command: ChangePartyRoleState.

Input:
- Party public UUID
- role CUSTOMER or SUPPLIER
- target state ACTIVE or INACTIVE
- expected positive version
- reason
- idempotency key

Trusted context:
- ActorId
- CompanyId
- optional BranchId
- CorrelationId

Application checks:
- party.role.manage
- valid Party public UUID
- role CUSTOMER/SUPPLIER
- state ACTIVE/INACTIVE
- expected version > 0
- deactivation requires non-empty reason
- reactivation does not require reason
- durable idempotency key

Persistence outcomes:
- Changed
- PartyNotFound
- RoleNotFound
- DuplicateOperation
- StaleVersion
- AlreadyInTargetState

Result:
- Party public UUID
- role
- resulting state
- incremented version
- correlation id

## Transaction / audit / idempotency / outbox

One PostgreSQL transaction:
1. resolve Party by trusted CompanyId + Party public UUID
2. resolve existing Party Role
3. validate expected version and current state
4. update state + version
5. insert Foundation idempotency operation
6. append audit
7. save
8. complete idempotency
9. commit

Audit:
- PartyRoleDeactivated.<ROLE> or PartyRoleReactivated.<ROLE>
- actor/company/correlation
- Party public UUID
- mandatory reason for deactivation
- no Finance payload
- no sensitive tax payload

Idempotency:
- required
- company-scoped role-state operation
- duplicate operation key maps to conflict under current Foundation semantics

Outbox:
- NOT REQUIRED
- PartyRoleDeactivated remains a candidate event
- no accepted consumer currently requires it in this slice

## API contract

Existing first activation endpoint remains unchanged:
- POST /api/v1/parties/{partyPublicId}/roles

New lifecycle endpoint:
- POST /api/v1/parties/{partyPublicId}/roles/{role}/state

Header:
- Idempotency-Key required

Body:
- state: ACTIVE or INACTIVE
- version: positive expected role version
- reason: required when state=INACTIVE, otherwise optional/null

No CompanyId field.

Expected mapping:
- unauthenticated → 401
- authenticated without party.role.manage → 403
- invalid role/state/version/idempotency → 400
- missing deactivation reason → 400
- Party absent from trusted company → 404
- role absent on Party → 404
- duplicate operation → 409
- stale version → 409 Concurrency
- already target state → 409
- success → 200

## UI contract

Reuse existing /parties/new journey.

After successful Party create + role activation:
- role action remains available as lifecycle control instead of staying permanently disabled
- activated role is shown ACTIVE with current version retained in page state
- next action for ACTIVE role is deactivate
- deactivation requires a reason input before request
- after successful deactivation role is shown INACTIVE and action offers reactivate
- reactivation sends the returned/current expected version and no mandatory reason
- after successful reactivation state returns ACTIVE
- each mutation uses a fresh idempotency key
- request contains no CompanyId
- 401/403/404/409/validation errors remain explicit
- failure does not erase Party, the other role, or Tax Identity state

The page does not claim to manage pre-existing Parties because Party read/detail is not yet implemented.

## Security / privacy

- party.role.manage enforced in API policy and handler
- authentication alone is insufficient
- Company scope comes from trusted execution context
- route Party UUID does not bypass company filtering
- UI state is not authorization
- no tax identity value is involved
- no role lifecycle action gains Finance authority

## Targeted verification

Normal development:
- .NET Release build
- missing party.role.manage denied before persistence
- invalid role/state/version
- missing deactivation reason
- reactivation reason not required
- trusted CompanyId scope
- Party-not-found
- Role-not-found
- duplicate operation
- stale version → concurrency conflict
- same-state transition → conflict
- successful ACTIVE→INACTIVE increments version
- successful INACTIVE→ACTIVE increments version
- audit action/reason
- other role not mutated by write contract
- no EF model change / no migration required
- frontend typecheck/tests/build
- UI activation → deactivation request contract
- UI deactivation → reactivation state/version contract
- no client CompanyId
- API/OpenAPI smoke
- TEST deploy/readiness/smoke

Remote smoke may prove:
- /parties/new 200
- unauthenticated lifecycle POST 401
- health/readiness 200
- OpenAPI lifecycle route present
- migration count remains 5

A real authenticated role lifecycle mutation is not required for normal TEST smoke unless an existing safe principal/grant path exists; no auth bypass may be invented.

## Full Test Day pending

- concurrent deactivate/deactivate
- deactivate vs reactivate race
- activate vs deactivate race
- role-state transition racing Sales/Purchasing document creation
- cross-company IDOR
- broad permission matrix
- authenticated browser create → activate → deactivate → reactivate
- CUSTOMER inactive while SUPPLIER remains active in consuming modules
- Finance proof that lifecycle creates no ledger/balance effect
- stale multi-client role edit
- performance/load/security regression
- backup/restore

## Decision

The next dependency-minimal Parties implementation work package is **PARTY-IMP-004 — Manage Party Role Lifecycle**.

Status: IMPLEMENTED / COMPLETED.

Canonical completion evidence:
- `docs/plan/03-cariler/party-imp-004-implementation.md`
- Foundation Build run `35910331829` — SUCCESS
- Foundation Test Deploy run `35910331820` — SUCCESS

No Contact/Address, Party lifecycle, Merge, fuzzy duplicate engine, Tax Identity disclosure/lifecycle, provider/legal integration or Finance behavior is included.
