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


## P5 first implementation readiness

Current first-slice analysis:
- `docs/plan/03-cariler/p5-first-slice-readiness.md`

Selected candidate:
- Create Party Core Identity.

Status:
- SCOPE DEFINED;
- IMPLEMENTATION BLOCKED;
- dedicated implementation work-package ID NOT ASSIGNED.

Blocking decisions:
- Mars-owned permission authority/evaluation for server-side `party.create`;
- initial Party Code assignment authority;
- minimum soft duplicate-warning contract or explicit first-slice deferral;
- physical Company reference strategy while Foundation has no Company table.

No Party C#/EF migration/API/TypeScript implementation may start until these blockers are resolved.
