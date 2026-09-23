# P5 Parties — First Vertical Slice Readiness

Status: READY FOR IMPLEMENTATION
Date: 2026-09-23
Phase: P5 — Core application implementation
Module: Parties
Implementation work package: PARTY-IMP-001
Title: Create Party Core Identity

## Objective

Freeze the smallest coherent first Parties implementation vertical slice from PLAN-003 and PLAN-010 contracts before Party schema/code mutation.

The selected first slice is:

**Create Party Core Identity**

It creates one company-scoped PERSON/ORGANIZATION Party before optional commercial role activation.

## Repository basis

Repository:
- `ufukunal/MarsOtomasyon`

Branch:
- `main`

Original blocker-resolution starting HEAD:
- `f47c6e65cdd0b147c5d8dfebc286931b6bd044cb`

P4 Foundation implementation:
- COMPLETED
- FW-IMP-001 through FW-IMP-008 completed

Planning metric remains:
- master section-8 sequence: 9 / 30 = 30.0%
- P2 core commercial: 8 / 8 = 100.0%

## Scope

### Included

Authoritative entity:
- Party.

Create fields:
- Party Code;
- kind PERSON / ORGANIZATION;
- legal name;
- optional display/trade name.

Trusted request context:
- ActorId;
- CompanyId;
- optional BranchId carried by Foundation but not Party identity scope;
- CorrelationId.

Initial state:
- ACTIVE.

Technical behavior:
- server-side `party.create`;
- retry-safe idempotency;
- audit;
- PostgreSQL transaction;
- protected API;
- minimum Mars.Web create surface.

### Deferred

Not in PARTY-IMP-001:
- Party Role;
- Tax Identity;
- Contact Person;
- Communication Point;
- Address;
- Party External Mapping;
- Party Merge Lineage;
- Finance projections;
- Sales/Purchasing consumption;
- soft/fuzzy duplicate candidate UI;
- Settings/Numbering allocator;
- permission administration UI/role model.

## Why this is the smallest correct slice

The frozen Party workflow creates the Party identity before optional CUSTOMER/SUPPLIER role activation.

PARTY-IMP-001 therefore proves the real Parties module boundary with one authoritative aggregate and no cross-module business dependency.

Effects:
- master: CREATE;
- reservation: NONE;
- stock: NONE;
- account: NONE;
- cash/bank: NONE;
- cost: NONE;
- audit: REQUIRED.

`PartyCreated` outbox remains optional because no accepted consumer is required by this slice.

## PARTY-BLK-001 — Permission authority/evaluation

Status: RESOLVED.

Decision:
- ADR-0005 — Mars ERP Permission Authority Baseline.

Authoritative permission truth:
- Mars-owned PostgreSQL effective Permission Grant;
- initial grant grain = ActorId + CompanyId + PermissionCode;
- `party.create` is the first concrete Party permission.

Application:
- persistence-neutral permission evaluator contract under Foundation/Application;
- Party create handler enforces `party.create`.

Infrastructure:
- PostgreSQL-backed evaluator;
- current grant state is authoritative.

API:
- endpoint may apply an ASP.NET Core policy backed by the same evaluator;
- handler-level enforcement remains required.

Security:
- authentication alone is not authorization;
- token/cookie/user claims are not sole ERP permission authority;
- client cannot choose CompanyId;
- revocation is visible to subsequent authoritative evaluations;
- no OpenIddict permission authority is introduced.

Deferred:
- role/group administration model;
- production permission-administration UI;
- branch-specific grants until a concrete module requires them.

Logical DB amendments:
- Foundation Permission Grant added to module ownership/entity catalog/constraint contract.

## PARTY-BLK-002 — Party Code assignment authority

Status: RESOLVED for PARTY-IMP-001.

Decision:
- PARTY-IMP-001 receives `partyCode` as a required create-command/API/UI input.
- The first slice does not allocate Party Code.
- No auto-number series or exact format is invented.
- Settings/Numbering remains owner of any future configurable allocation/series policy.
- A future allocator may prefill/provide the same create input without changing the Party aggregate contract.

Validation for the first slice:
- required;
- non-empty/non-whitespace;
- leading/trailing whitespace is rejected rather than silently normalized;
- no regex/prefix/length business format is invented beyond a technical storage bound selected during physical implementation;
- no case-folding or locale normalization is invented.

Durable uniqueness:
- exact stored Party Code is unique within CompanyId;
- PostgreSQL unique constraint is authoritative for concurrent duplicate create.

UI:
- `/parties/new` exposes Party Code as an editable required field for this initial slice.

API:
- `POST /api/v1/parties` accepts required `partyCode`.

PARTY-IMP-001 contains no Party Code edit command, so later mutation/renumbering policy is outside this slice.

## PARTY-BLK-003 — Soft duplicate warning

Status: RESOLVED by explicit first-slice deferral.

Decision:
- soft/fuzzy duplicate candidate detection is NOT implemented in PARTY-IMP-001;
- no matching algorithm, threshold, score or normalization is invented.

Reason:
- PARTY-IMP-001 is explicitly **Core Identity**, not full PLAN-003 Create Party parity;
- Tax Identity, Contact, Address and External Mapping are already deferred;
- the only currently available duplicate signal would be legal/display-name similarity;
- frozen sources intentionally do not define its fuzzy algorithm.

PARTY-IMP-001 still enforces deterministic constraints available in its scope:
- company-scoped exact Party Code uniqueness;
- public identity uniqueness;
- idempotency uniqueness.

Future requirement:
- before Parties claims full PLAN-003 Create Party workflow completion, a later Party slice must implement the accepted soft duplicate review/keep-separate behavior and tax-identity deterministic collision rules.

This deferral does not change the frozen rule that fuzzy matches never auto-merge.

## PARTY-BLK-004 — Physical Company reference

Status: RESOLVED.

Decision:
- PARTY-IMP-001 stores required `CompanyId` UUID on Party;
- CompanyId comes only from trusted Mars execution context;
- no client-supplied company field is accepted;
- no physical Company FK is created in PARTY-IMP-001 because the repository currently has no physical Company master table.

Rationale:
- inventing a Foundation Company table would exceed the first Party slice and would require undefined Company physical attributes/lifecycle;
- logical Company 1→N Party ownership remains enforced through required CompanyId scope and query/mutation filtering;
- cross-company references remain forbidden.

Physical constraints:
- CompanyId NOT NULL;
- unique Party Code scope includes CompanyId;
- all Party queries/mutations filter by trusted CompanyId.

Future:
- when a physical Company authority is accepted, a later additive migration may introduce an FK after data validation/backfill review.

## PARTY-IMP-001 physical DB contract

Owning module:
- Parties.

Candidate physical schema:
- `parties`.

Party persistence:
- internal key: BIGINT-style surrogate;
- public id: UUID;
- company_id: UUID, required, no FK in this slice;
- party_code: required text;
- kind: PERSON / ORGANIZATION;
- legal_name: required text;
- display_name: optional text;
- state: ACTIVE / INACTIVE / MERGED representation, initial ACTIVE;
- optimistic version token;
- creation/audit timestamps only where consistent with existing persistence conventions.

Required guarantees:
- PK internal key;
- unique public UUID;
- unique CompanyId + PartyCode;
- NOT NULL on required fields;
- check/valid representation for kind;
- check/valid representation for state;
- optimistic stale-write support.

No table is added for:
- role;
- tax identity;
- contact;
- communication point;
- address;
- mapping;
- merge lineage;
- projection.

Migration:
- additive;
- no backfill;
- no destructive operation;
- no existing Party data;
- migration-specific role only;
- TEST rehearsal before completion.

## PARTY-IMP-001 authorization contract

Permission:
- `party.create`.

Evaluation:
- trusted ActorId + CompanyId + PermissionCode;
- current PostgreSQL grant authority through ADR-0005.

Enforcement:
- endpoint defense-in-depth policy;
- Application handler authoritative command check;
- UI visibility is not authorization.

No permission administration screen is in scope.

## PARTY-IMP-001 Application/API contract

Candidate command:
- CreateParty.

Input:
- partyCode;
- kind;
- legalName;
- optional displayName;
- idempotencyKey.

Trusted context:
- actor/company/correlation from `IExecutionContext`.

Result:
- Party public UUID;
- Party Code;
- kind;
- legal/display identity;
- ACTIVE state;
- concurrency version if part of public mutation contract;
- correlation id through normal response/error conventions.

Candidate endpoint:
- `POST /api/v1/parties`.

Expected mappings:
- unauthenticated → 401;
- authenticated without `party.create` → 403;
- invalid input → deterministic validation response;
- duplicate company + Party Code → 409;
- duplicate idempotency request → accepted idempotent semantics defined by existing Foundation mechanism.

No CompanyId is accepted from request JSON.

## PARTY-IMP-001 UI contract

Candidate route:
- `/parties/new`.

Minimum fields:
- Party Code;
- kind;
- legal name;
- optional display/trade name.

Requirements:
- use Mars.UI;
- company context may be displayed as trusted/non-editable context;
- preserve entered values on validation/conflict;
- accessible labels/focus/keyboard flow;
- no Customer/Supplier duplicate master form;
- no balance/risk fields;
- no role/tax/contact/address UI in this slice;
- no fuzzy duplicate panel in this slice.

## Audit / idempotency / outbox

Audit:
- REQUIRED;
- actor/company/action/Party public identity/time/correlation;
- no arbitrary sensitive before/after dump.

Idempotency:
- REQUIRED for retry-safe create;
- PostgreSQL Foundation idempotency remains authoritative;
- scope includes trusted company and Create Party operation;
- retry cannot create a second Party.

Outbox:
- NOT REQUIRED in PARTY-IMP-001 because PartyCreated is optional and no accepted consumer exists.
- adding a consumer later may add the event through a separate source-backed slice.

## Targeted verification for PARTY-IMP-001

Normal development only:
- .NET Release build;
- Party create Application/domain tests;
- required Party Code;
- invalid kind;
- required legal name;
- company-scoped exact Party Code duplicate conflict;
- same exact Party Code in a different trusted company remains independently scoped;
- client cannot override CompanyId;
- `party.create` positive/negative evaluator tests;
- endpoint 401/403 behavior;
- idempotent retry;
- audit write;
- EF model/constraint checks;
- migration safety and pending-model check;
- frontend typecheck/tests/build;
- small API/OpenAPI smoke;
- TEST migration/deploy/readiness/smoke when deployable.

Heavy authorization matrix, PostgreSQL concurrency and browser E2E remain Full Test Day only.

## Work-package decision

All blocker-resolution gates are now closed for the first slice.

Dedicated implementation work package:
- **PARTY-IMP-001 — Create Party Core Identity**

Status:
- READY FOR IMPLEMENTATION.

No Party implementation code or migration was written by the blocker-resolution session that froze this contract.

## Full Test Day pending

- concurrent Party Code create race;
- future concurrent tax-identity collision;
- stale Party edit;
- cross-company IDOR matrix;
- broad permission matrix;
- browser Party lifecycle E2E;
- high-volume fuzzy duplicate candidate search;
- downstream snapshot immutability;
- security/privacy regression.

## Sources

Governance/state:
- `docs/plan/ai-cmd.md`
- `docs/ai/README.md`
- `docs/ai/autocomplete.md`
- `docs/ai/skill-router.md`
- `docs/ai/session-execution-protocol.md`
- `docs/plan/master-project-plan.md`

Party:
- `docs/plan/03-cariler/plan.md`
- `docs/plan/03-cariler/workflows.md`
- `docs/plan/03-cariler/forms.md`
- `docs/plan/03-cariler/data-contract.md`
- `docs/plan/03-cariler/permissions.md`

DB:
- `docs/db/00-domain-dictionary.md`
- `docs/db/01-design-principles.md`
- `docs/db/02-module-ownership.md`
- `docs/db/03-entity-catalog.md`
- `docs/db/04-relationships.md`
- `docs/db/07-constraints-and-concurrency.md`
- `docs/db/08-index-access-patterns.md`
- `docs/db/09-migration-conventions.md`

Foundation/security:
- `docs/plan/decisions/ADR-0003-identity-openiddict-baseline.md`
- `docs/plan/decisions/ADR-0005-mars-erp-permission-authority.md`
- `docs/plan/01-foundation/framework-plan.md`
- `src/Mars.Api/Foundation/Authentication/`
- `src/Mars.Api/Program.cs`
- `src/Mars.Application/Foundation/`
- `src/Mars.Infrastructure/Identity/`
- `src/Mars.Infrastructure/Persistence/`
