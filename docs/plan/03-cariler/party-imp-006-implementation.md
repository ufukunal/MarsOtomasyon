# PARTY-IMP-006 — Party Master Completion Tranche

Status: COMPLETED
Date: 2026-09-24

## Objective

Implement one broad, coherent Party master tranche instead of splitting every remaining Party capability into separate micro-packages.

Included:
- Party directory/list/detail/read;
- live legal/display identity edit;
- Contact Person and Communication Point create/read/lifecycle;
- Address create/update/read/lifecycle/default-by-purpose;
- existing TR VKN/TCKN read/masking/lifecycle;
- Party External Mapping create/read/lifecycle;
- explicit same-company Party Merge with source → survivor lineage;
- required Party permissions, API, Mars.Web UI, PostgreSQL persistence, migration, audit, durable idempotency, concurrency handling and targeted verification.

Explicitly excluded:
- fuzzy duplicate candidate generation/scoring/thresholds;
- Party Reactivation;
- provider/GİB verification and enrollment;
- generic non-TR Tax Identity;
- Communications consent/preferences;
- consuming Sales/Purchasing eligibility;
- Finance balance/risk/settlement;
- production deployment;
- Full Test Day.

## Repository evidence

Readiness:
- `docs/plan/03-cariler/p5-party-master-completion-readiness.md`

Implementation commit:
- `d3b21ab936bfb3b9c6d602d799e8ab587ea4ee05`

Migration-verification fix:
- `20f9635d58ff0289e0aea098f4ec0e47399c7d93`

Generated migration commit:
- `2a4bb99e12e65c0a4c121755d764e7bca7feda85`

Final verification trigger / tested commit:
- `55b7e78bc2eabcf986e8318f60296f0f3f9cd223`

## Implemented domain/application surface

Party:
- company-scoped list/detail;
- legal/display identity edit;
- expected-version optimistic concurrency;
- MERGED mutation protection;
- audit + durable idempotency.

Contact Person:
- normalized Party child;
- create;
- read through Party detail;
- ACTIVE / INACTIVE lifecycle;
- optimistic version;
- company-compatible ownership.

Communication Point:
- normalized Contact child;
- type/value/purpose/primary;
- create;
- read;
- ACTIVE / INACTIVE lifecycle;
- optimistic version;
- primary handling within the accepted contact/type scope.

Address:
- normalized Party child;
- BILLING / SHIPPING / GENERAL;
- structured address fields;
- create/update/read;
- default-by-purpose handling;
- ACTIVE / INACTIVE lifecycle;
- optimistic version.

TR Tax Identity:
- existing VKN/TCKN authority preserved;
- authorized read;
- masked ordinary detail;
- full value only with `party.tax_identity.read_full`;
- ACTIVE / INACTIVE lifecycle;
- existing active company + jurisdiction + scheme + value uniqueness preserved;
- raw value is not introduced into audit payload.

Party External Mapping:
- normalized Party child;
- system/source code;
- normalized account scope;
- external identity;
- ACTIVE / INACTIVE lifecycle;
- deterministic active company + system + account-scope + external-identity uniqueness;
- provider/external ID remains a mapping, not canonical Party identity.

Party Merge:
- explicit source and survivor;
- same trusted company;
- distinct source/survivor;
- source and survivor expected-version checks;
- mandatory reason;
- explicit source identity / role / contact / address / tax / external-mapping move choices;
- conflict detection before moving incompatible unique data;
- source becomes MERGED;
- durable merge lineage;
- source remains historically addressable;
- no automatic fuzzy merge;
- no Finance/stock/account/cash-bank/cost effect;
- no historical document snapshot rewrite.

## Permission surface

Added/frozen in implementation:
- `party.read`
- `party.edit`
- `party.reactivate` namespace entry only; no Reactivation endpoint/handler
- `party.role.read`
- `party.contact.read`
- `party.contact.manage`
- `party.address.read`
- `party.address.manage`
- `party.tax_identity.read`
- `party.tax_identity.read_full`
- `party.duplicate.review` namespace entry only
- `party.duplicate.keep_separate` namespace entry only
- `party.merge`
- `party.external_mapping.read`
- `party.external_mapping.manage`

Existing create/deactivate/role/tax-manage permissions remain.

Permission constants alone do not imply implementation of excluded Reactivation/fuzzy-review workflows.

## Physical database contract

Generated migration:
- `src/Mars.Infrastructure/Persistence/Migrations/Parties/20260923233312_PartyImp006PartyMasterCompletion.cs`

New normalized tables:
- `parties.contacts`
- `parties.communication_points`
- `parties.addresses`
- `parties.external_mappings`
- `parties.merge_lineage`

Key constraints include:
- UUID public identity uniqueness;
- Party/company compatible foreign keys for Party children;
- Contact ownership for Communication Point;
- one active default Address per Party + purpose;
- deterministic active External Mapping uniqueness;
- one merge lineage per merged source;
- source/survivor distinct check;
- ACTIVE/INACTIVE state and positive version checks.

Delete behavior preserves master/history relationships through restrictive foreign keys.

Committed migration count:
- before PARTY-IMP-006: 5;
- after PARTY-IMP-006: 6.

Final pending-model check:
- PASS — no model changes since the last migration.

## API

Protected Party master endpoints include:
- `GET /api/v1/parties`
- `GET /api/v1/parties/{partyPublicId}`
- `PUT /api/v1/parties/{partyPublicId}`
- contact create/state;
- communication create/state;
- address create/update/state;
- Tax Identity state;
- External Mapping create/state;
- explicit merge.

Existing create/deactivate/role/Tax Identity add endpoints remain.

Company scope comes from trusted execution context; request payloads do not establish company authority.

## Mars.Web

Added Party master route:
- `/parties`

The Party master UI supports:
- list/search/detail;
- legal/display identity edit;
- Contact + Communication operations;
- Address operations;
- Tax Identity visibility/lifecycle;
- External Mapping operations;
- explicit merge controls.

Existing `/parties/new` remains available.

No React/Vue/Angular/Bootstrap/Tailwind/jQuery was introduced.

## Final verification

### Foundation Build

Run:
- `35934312246`

Job:
- `107427788711`

Tested SHA:
- `55b7e78bc2eabcf986e8318f60296f0f3f9cd223`

Evidence:
- frontend tests: 18 / 18 PASS;
- frontend type/build/static verification: PASS;
- .NET Release build: PASS;
- warnings: 0;
- errors: 0;
- Foundation targeted tests: 61 / 61 PASS;
- Party master permission/masking/edit/merge/domain/model targeted tests: PASS;
- EF pending-model: PASS — no changes since last migration;
- API smoke: PASS;
- project references: PASS.

### Foundation Test Deploy

Run:
- `35934312470`

Job:
- `107427788946`

Status:
- SUCCESS

Evidence:
- frontend verification: PASS;
- Release build + 61 targeted tests: PASS;
- migration safety: PASS for 6 committed migrations;
- EF pending-model: PASS;
- remote TEST preflight: PASS;
- preflight migration count: 5;
- API image: PASS;
- migrator image: PASS;
- Web image: PASS;
- migration apply: PASS;
- runtime grants: PASS;
- Compose config: PASS;
- health gate: PASS;
- deployed migration count: 6;
- deploy result: PASS.

Runner-to-TEST smoke:
- `/` → 200
- `/components` → 200
- `/proof` → 200
- `/parties` → 200
- `/parties/new` → 200
- `/health/live` → 200
- `/health/ready` → 200
- protected Party list/detail/edit/contact/address/external-mapping/merge routes → 401 unauthenticated
- existing protected Party mutation routes → 401 unauthenticated
- OpenAPI → 200
- smoke result: PASS.

A real authenticated Party master mutation against TEST is not claimed.

## Verification history

Initial implementation commit `d3b21ab...` triggered failed runs:
- Foundation Build `35933838904`: compile failure in PARTY-IMP-006 test setup due incorrect PostgreSqlRuntimeOptions namespace reference;
- Foundation Test Deploy `35933838879`: build worker/MSBuild failure before deployment;
- migration generation `35933838870`: migration was generated but the workflow's pending-model verification sequence still failed.

Commit `20f9635d...` fixed the test namespace and migration-generation verification sequencing.

On `20f9635d...`:
- migration generation run `35934132531` succeeded and generated/committed the migration;
- Foundation Build `35934132029` and Test Deploy `35934132032` failed because the model change existed before the generated migration commit became the checked-out source. This is retained as verification history, not hidden.

Migration commit `2a4bb99e...` added the generated migration/model snapshot.

Commit `55b7e78b...` restored the migration-generation workflow to manual/read-only mode and triggered final verification.

Final Build and Test Deploy both succeeded.

## Deferred / remaining Party semantics

Still deferred:
- soft/fuzzy duplicate candidate generation and review because no accepted normalization/similarity/score/threshold exists;
- Party Reactivation because PLAN-003 requires current duplicate/legal identity validation rerun and the accepted prerequisite interpretation remains unresolved;
- provider/GİB verification/enrollment;
- non-TR Tax Identity;
- Communications consent/preferences;
- consuming Sales/Purchasing Party eligibility;
- Finance integration.

These deferrals do not invalidate PARTY-IMP-006 completion because they were explicitly outside its frozen scope.

## Full Test Day pending

Deferred heavy evidence includes:
- high-contention Party/child/merge concurrency;
- broad authenticated browser E2E;
- full permission/IDOR matrix;
- high-volume Party search/duplicate scenarios;
- PII/security regression;
- performance/load;
- backup/restore;
- consuming-module eligibility races;
- provider reconciliation when introduced.

## Completion decision

PARTY-IMP-006 is COMPLETED.

The broad Party master tranche has:
- real source implementation;
- generated committed migration;
- no pending EF model change;
- successful targeted Build verification;
- successful remote TEST migration/deployment/smoke;
- explicit preserved deferrals for fuzzy duplicate and Reactivation semantics.

Planning metrics are not changed by this implementation closure:
- active state continues to report master 9 / 30 = 30.0%;
- P2 remains 8 / 8 = 100.0%.

The existing repository inconsistency where `docs/db/acceptance-criteria.md` mentions 10 / 30 = 33.3% is not silently normalized by this package.
