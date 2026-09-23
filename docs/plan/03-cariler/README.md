# Party / Customer / Supplier Module Plan

Status: PLAN-003 COMPLETED / FROZEN — planning only.

## Purpose

Parties owns the live master identity of external business subjects used by Sales, Purchasing, Finance and later modules.

Canonical model:

Party
→ one or more company-scoped roles
→ Customer and/or Supplier
→ Contacts / Addresses / Tax Identities
→ consuming documents take immutable snapshots

A Customer and Supplier are roles of one Party identity, not separate competing master records.

## Process ownership

- Parties: live identity, role assignment, contacts, addresses, tax identities, lifecycle, duplicate detection and merge lineage.
- Sales: Quote/Order/Invoice commercial documents and their historical Party snapshots.
- Purchasing: future Purchase documents and supplier snapshots.
- Finance: account ledger, receivable/payable balance, credit/risk/hold truth and settlement.
- Settings/Numbering: human-visible numbering formats and configurable commercial reference catalogs.

## Frozen invariants

- Party is company-scoped authoritative master identity.
- Party kind is PERSON or ORGANIZATION.
- One Party may hold CUSTOMER and SUPPLIER roles simultaneously.
- Role activation does not create a second Party identity.
- Canonical Party code is company-scoped and role-neutral; role-specific references cannot become duplicate identity truth.
- Live master edits never mutate historical document snapshots.
- Customer/supplier current balance is Finance-ledger-derived, never mutable Party data.
- No automatic customer-payable/supplier-receivable netting is introduced by Parties.
- Credit/risk/hold is Finance-owned; Parties may consume/display a read-only signal.
- Duplicate candidates are warnings except deterministic identity collisions; fuzzy matching never auto-merges.
- Merge is logical/audited: survivor + MERGED source lineage; no historical hard delete.
- INACTIVE and MERGED Parties cannot be newly selected for transactions, while history remains readable.
- Cross-company use requires a separate Party in the target company; no silent cross-company sharing.
- PostgreSQL remains authoritative; projections/search indexes/caches are rebuildable.

## Turkey identity/e-document reference

For Turkish legal/e-document use:
- VKN is represented as a 10-digit tax identity scheme.
- TCKN is represented as an 11-digit identity scheme.
- e-Fatura recipient data requires VKN/TCKN plus legal name/name-surname and required address information according to GİB guidance.
- Not every Party is forced to have a Turkish tax identity; requirement is triggered by jurisdiction and downstream legal-document rules.
- Checksum/enrollment/provider validation belongs integration/legal validation at implementation time and must follow current official rules.

## Files

- plan.md — ownership, decisions, boundaries and lifecycle.
- workflows.md — create/edit/role/deactivate/merge/snapshot workflows.
- forms.md — Party list/detail and master-data UX.
- data-contract.md — conceptual entities and authoritative/derived model.
- permissions.md — Party master and sensitive/high-risk actions.
- integrations.md — master-data events, external IDs and e-document identity boundary.
- reports.md — Party read/report projections.
- acceptance-criteria.md — PLAN-003 completion evidence.
- full-test-day.md — deferred heavy-test backlog.

## Out of scope

PLAN-003 does not define:
- physical SQL schema/migrations;
- C#/API/TypeScript implementation;
- exact customer credit-limit formula;
- receivable/payable settlement or netting;
- exact numbering string format;
- provider enrollment behavior;
- Purchasing workflow;
- CRM opportunity/activity engine;
- files/notes/tags without a later source-backed requirement.


## P5 implementation status

First implementation work package:
- PARTY-IMP-001 — Create Party Core Identity — COMPLETED.

Canonical readiness:
- `docs/plan/03-cariler/p5-first-slice-readiness.md`

Canonical implementation evidence:
- `docs/plan/03-cariler/party-imp-001-implementation.md`

Verification:
- Foundation Build `35885316247` — SUCCESS;
- Foundation Test Deploy `35885323206` — SUCCESS;
- frontend tests 12 / 12;
- Foundation targeted tests 35 / 35;
- committed Party migration applied to TEST;
- /parties/new 200;
- unauthenticated POST /api/v1/parties 401;
- live/ready 200 / 200;
- runner-to-TEST smoke PASS.

PARTY-IMP-001 intentionally remains narrower than full PLAN-003 Create Party parity.

Still deferred:
- Party Role;
- Tax Identity;
- Contact/Communication;
- Address;
- External Mapping;
- Merge Lineage;
- soft/fuzzy duplicate review;
- Settings/Numbering allocator.

Second implementation work package:
- PARTY-IMP-002 — Activate Party Role — COMPLETED.

Readiness:
- `docs/plan/03-cariler/p5-second-slice-readiness.md`

Canonical implementation evidence:
- `docs/plan/03-cariler/party-imp-002-implementation.md`

Verification:
- Foundation Build `35889499695` — SUCCESS;
- Foundation Test Deploy `35889499708` — SUCCESS;
- frontend tests 13 / 13;
- Foundation targeted tests 40 / 40;
- committed Party Role migration applied to TEST;
- /parties/new 200;
- unauthenticated role POST 401;
- live/ready 200 / 200;
- runner-to-TEST smoke PASS.

PARTY-IMP-002 preserves the one-Party/multi-role model and creates no Finance posting.

Still deferred:
- role deactivate/reactivate;
- Tax Identity;
- Contact/Communication;
- Address;
- External Mapping;
- Merge Lineage;
- soft/fuzzy duplicate review;
- Settings/Numbering allocator.

Third implementation work package:
- PARTY-IMP-003 — Add Turkish Tax Identity.

Readiness:
- `docs/plan/03-cariler/p5-third-slice-readiness.md`

Status:
- READY FOR IMPLEMENTATION;
- implementation NOT STARTED.

Selected scope:
- existing trusted-company Party;
- TR jurisdiction only;
- VKN 10-digit / TCKN 11-digit structural identity;
- deterministic active company-level collision;
- `party.tax_identity.manage`;
- no checksum/provider/GİB enrollment claim;
- no raw tax identity audit/result disclosure.
