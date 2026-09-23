# PARTY-IMP-006 — Party Master Completion Tranche Readiness

Status: READY FOR IMPLEMENTATION

## Owner direction

The owner explicitly directed that Party implementation scope be broadened instead of creating a separate work package for every small capability.

PARTY-IMP-006 therefore groups coherent, source-backed PLAN-003 / PLAN-010 Party master work into one implementation tranche.

Normal technical implementation decisions belong to the active engineering skills and do not require owner escalation unless they introduce a new business rule, external-provider/legal rule or cross-module accounting/commercial behavior.

## Sources

Frozen contracts:
- `docs/plan/03-cariler/plan.md`
- `docs/plan/03-cariler/data-contract.md`
- `docs/plan/03-cariler/workflows.md`
- `docs/plan/03-cariler/forms.md`
- `docs/plan/03-cariler/permissions.md`
- `docs/plan/03-cariler/integrations.md`
- `docs/plan/03-cariler/acceptance-criteria.md`
- `docs/db/02-module-ownership.md`
- `docs/db/03-entity-catalog.md`
- `docs/db/04-relationships.md`
- `docs/db/07-constraints-and-concurrency.md`
- `docs/db/08-index-access-patterns.md`
- `docs/db/09-migration-conventions.md`

Implementation baseline:
- PARTY-IMP-001 through PARTY-IMP-005;
- current Party domain/application/persistence/API/Mars.Web code.

## Active skills

Primary:
- erp-domain-specialist
- accounting-finance-specialist

Reviewers:
- database-architect
- software-architect
- software-developer
- software-test-engineer

## Objective

Move Parties from the current narrow create/role/tax-add/deactivate surface toward a usable Party master in one package while preserving company, permission, privacy, audit, idempotency, concurrency, snapshot and Finance boundaries.

## Included capability set

### Party read/detail/edit

Implement Party directory/list/detail/read surfaces from the frozen UX contract.

Editable core identity:
- legal name;
- display/trade name.

Preserve Party Code identity/uniqueness, company ownership and PERSON/ORGANIZATION kind unless a separate accepted rule explicitly allows mutation.

Permissions:
- `party.read`
- `party.edit`

Mutations use expected-version optimistic concurrency, audit and durable idempotency.

### Contact Person / Communication Point

Implement normalized Party-owned Contact Person and Communication Point records.

Source-backed semantics:
- Contact Person: name, optional title/role label, purpose, ACTIVE/INACTIVE;
- Communication Point: type, value, purpose, primary flag, ACTIVE/INACTIVE;
- multiple records per Party;
- historical document contact snapshots remain document-owned.

Permissions:
- `party.contact.read`
- `party.contact.manage`

Communications consent, opt-in/out and channel-preference authority are excluded.

### Address

Implement normalized Party-owned Address records and lifecycle.

Source-backed semantics:
- structured address components;
- country/jurisdiction;
- label;
- purpose: BILLING / SHIPPING / GENERAL;
- ACTIVE/INACTIVE;
- explicit default/primary-by-purpose behavior;
- historical document address snapshots remain unchanged after master edits.

Permissions:
- `party.address.read`
- `party.address.manage`

Physical component columns are a database implementation decision but must remain relational and must not introduce new business semantics.

### Existing TR Tax Identity completion

Complete the operational surface around existing TR VKN/TCKN records.

Include:
- authorized read/list;
- masked ordinary views;
- full value only with `party.tax_identity.read_full`;
- ACTIVE/INACTIVE lifecycle;
- expected-version protection;
- deterministic active company + jurisdiction + scheme + value collision protection;
- identity replacement/history behavior that never rewrites historical document snapshots.

Permissions:
- `party.tax_identity.read`
- `party.tax_identity.read_full`
- `party.tax_identity.manage`

Exclude checksum/provider/GIB verification, enrollment claims and non-TR schemes.

### Party External Mapping

Implement Party-owned External Mapping.

Semantics:
- provider/source/system;
- external identity;
- optional account/provider scope where applicable;
- ACTIVE/INACTIVE;
- company-scoped Party ownership;
- deterministic uniqueness in provider/account/system identity scope;
- external/provider ID never becomes canonical Party identity;
- duplicate retry/import cannot silently create another mapping.

Permissions:
- `party.external_mapping.read`
- `party.external_mapping.manage`

Provider-specific marketplace behavior is excluded.

### Explicit Party Merge

Implement the frozen high-risk merge workflow as explicit source/survivor selection. Fuzzy candidate generation is not required to manually select a merge pair.

Preconditions/effects:
- same company;
- distinct source/survivor;
- `party.merge`;
- current state/version checks;
- mandatory merge reason;
- explicit conflict choices for live master data;
- preserve accepted unique roles/contacts/communication/address/tax/external mappings according to conflict resolution;
- create durable source → survivor merge lineage;
- source → MERGED;
- source remains historically addressable;
- new selection resolves to survivor;
- historical document snapshots remain unchanged;
- no Finance/stock/account/cash-bank/cost effect;
- no automatic receivable/payable netting;
- audit + durable idempotency.

No automatic merge is permitted.

## Explicitly deferred

### Soft/fuzzy duplicate candidate generation

No accepted normalization/similarity/score/threshold exists.

Do not invent fuzzy candidate scoring, ranking or automatic merge.

### Party Reactivation

PLAN-003 requires current duplicate/legal identity validation rerun. Repository sources still do not resolve whether current deterministic/local validation alone is sufficient or whether accepted soft-duplicate review must exist first.

Reactivation is outside PARTY-IMP-006, so this ambiguity does not block the active package.

### Other exclusions

- provider/GIB verification/enrollment;
- generic non-TR Tax Identity;
- Communications consent/preferences;
- Sales/Purchasing consuming eligibility;
- Finance balance/risk/settlement;
- production deployment;
- Full Test Day.

## Database / migration boundary

Expected normalized Party-owned records:
- Contact Person;
- Communication Point;
- Address;
- Party External Mapping;
- Party Merge Lineage.

Requirements:
- PostgreSQL authority;
- relational/3NF master structures;
- company-compatible FKs;
- BIGINT internal keys and UUID public IDs where externally addressed;
- optimistic version on mutable masters;
- deterministic unique constraints;
- history-preserving delete behavior;
- schema only through EF migration;
- no JSON/EAV relational escape;
- no no-op migration.

Prefer one coherent additive PARTY-IMP-006 migration when practical.

## API / UI

Expand protected `/api/v1/parties` surfaces for the included Party master capabilities.

Mars.Web evolves from the current `/parties/new` journey toward frozen Party list/detail/edit sections using the existing Mars.UI/frontend architecture.

Server authorization remains authoritative; UI hiding is not security.

## Acceptance evidence

Normal development:
- targeted Party domain/application/persistence tests;
- permission/company isolation;
- duplicate/idempotency/stale-version checks;
- Tax Identity masking/full-read checks;
- frontend type/test/build;
- Release build;
- generated migration + migration safety;
- EF pending-model clean;
- TEST migration/deployment/smoke;
- unauthenticated protected-route checks.

Do not claim authenticated end-to-end mutation unless direct evidence exists.

Heavy concurrency, broad security matrix, browser E2E, performance/load, backup/restore and high-volume duplicate search remain Full Test Day.

## SOURCE / INFERENCE / UNKNOWN / BLOCKED

SOURCE:
- PLAN-003 freezes Party/contact/address/tax/external mapping/merge ownership and lifecycle.
- PLAN-010 freezes normalized entities, relationships, collision/concurrency outcomes and migration discipline.
- owner explicitly requested broader implementation scope.

INFERENCE:
- grouping these capabilities into one package reduces coordination overhead without changing domain authority.

UNKNOWN:
- fuzzy duplicate algorithm;
- Reactivation duplicate/legal-identity rerun prerequisite interpretation;
- provider-specific External Mapping behavior beyond the generic mapping contract.

BLOCKED:
- only those explicitly excluded semantics.
- PARTY-IMP-006 itself is not blocked.

## Decision

Assigned:
`PARTY-IMP-006 — Party Master Completion Tranche`

Status:
`READY FOR IMPLEMENTATION`
