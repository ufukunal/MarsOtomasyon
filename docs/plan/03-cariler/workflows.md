# Party / Customer / Supplier Workflows

Status: FROZEN — PLAN-003

## 1. Create Party

Purpose:
create one company-scoped external subject master before assigning commercial roles.

Flow:
1. choose Party kind PERSON or ORGANIZATION;
2. enter legal/display identity;
3. enter applicable tax identity;
4. run deterministic duplicate checks + candidate warnings;
5. create Party;
6. optionally activate CUSTOMER and/or SUPPLIER role;
7. add contacts/addresses;
8. audit actor/time/company.

Effects:
- DOC/master: CREATE
- RES/STOCK/ACCOUNT/CASH-BANK/COST: NONE
- outbox: optional PartyCreated master-data event for accepted consumers

Duplicate behavior:
- deterministic tax identity collision → conflict, do not create duplicate active Party.
- fuzzy candidate → show warning; no auto-merge.
- authorized override of warning requires reason.

## 2. Assign role

Party can hold multiple roles.

Add CUSTOMER:
- activate Customer role in same company Party.
- no new legal identity.
- no receivable/account posting.

Add SUPPLIER:
- activate Supplier role.
- no payable/account posting.

Disable role:
- prevents new use of that role by default;
- other roles remain active;
- historical documents remain.

Role activation/removal is audited.

## 3. Edit live identity

Editable live master:
- current legal/display identity;
- contacts;
- addresses;
- applicable tax identity records;
- defaults/references within accepted policy.

Rules:
- changes affect future selections only.
- posted/frozen document snapshots remain unchanged.
- deterministic identity collision blocks save.
- stale concurrent edit returns conflict; no silent last-write-wins planning assumption.

## 4. Address lifecycle

Create:
ACTIVE address with purpose(s).

Edit:
- future master value changes;
- historical document snapshots unaffected.

Deactivate:
- cannot be selected as a new default/source address;
- stays readable for history/audit.

Delete:
- physical deletion of historically referenced address is not required by PLAN-003 and must not be used to erase document history.
- P3 decides relational retention mechanics.

Selection:
- consuming document selects a current eligible address and snapshots required legal/operational fields at its accepted freeze point.

## 5. Contact lifecycle

Contacts/communication points:
ACTIVE → INACTIVE.

- multiple contacts allowed.
- purpose/primary designation is explicit.
- historical recipient/contact snapshots are document-owned after freeze.
- Communications consent/preference lifecycle is outside PLAN-003.

## 6. Tax identity lifecycle

- add jurisdiction + scheme + value;
- validate structural rules applicable to the scheme;
- deterministic duplicate check within company;
- mark/update verification metadata when a trusted verification source exists;
- legal document workflow revalidates its required identity contract when needed.

Changing a tax identity:
- does not rewrite posted documents;
- prior identity remains auditable when historical correctness requires lineage;
- exact persistence/versioning implementation belongs P3.

Turkey:
- VKN: 10 digits;
- TCKN: 11 digits;
- e-Fatura/e-Arşiv recipient validation follows current GİB rules.

## 7. Deactivate/reactivate Party

ACTIVE → INACTIVE:
- explicit action;
- reason required;
- Party unavailable for new counterparty selection by default;
- no ledger/document reversal;
- history remains readable.

INACTIVE → ACTIVE:
- explicit permission;
- audit;
- current duplicate/legal identity validation reruns.

Consuming modules decide how already-created drafts behave; Party module does not silently cancel them.

## 8. Duplicate review

Candidate signals:
- legal name similarity;
- exact/overlapping communication point;
- address similarity;
- external reference;
- tax identity.

Review outcomes:
- NOT_DUPLICATE;
- MERGE;
- KEEP_SEPARATE_WITH_REASON.

Only deterministic identity uniqueness can hard-block automatically before human review.

## 9. Merge

Preconditions:
- same company;
- actor has merge permission;
- survivor/source are distinct and not already terminally merged in conflict;
- identity/role/contact/address conflicts reviewed;
- reason supplied.

Flow:
1. preview source vs survivor;
2. select survivor;
3. resolve conflicting master attributes explicitly;
4. preserve all unique valid roles/contacts/addresses/tax/external mappings according to conflict choices;
5. create merge lineage;
6. source → MERGED;
7. future lookup/selection resolves to survivor;
8. historical documents retain original snapshot/reference lineage.

Merge effects:
- no ACCOUNT/CASH/BANK/STOCK/COST posting.
- no historical document mutation.
- no automatic ledger netting.
- master-data outbox event may notify projections/indexes.

## 10. Historical snapshot consumption

When Sales/Purchasing/Finance document needs counterparty snapshot:
1. validate Party/company/role eligibility;
2. select live legal identity/address/contact;
3. copy required snapshot values into document authority at document-defined freeze/post point;
4. retain Party reference for navigation/audit;
5. later Party changes cannot modify document snapshot.

## 11. Finance read projection

Authorized Party detail may query/display:
- current receivable balance;
- current payable balance;
- credit/risk/hold signal when Finance later defines one.

These are Finance projections:
- never editable from Party;
- never cached as Party authority;
- no automatic receivable/payable netting.

## 12. Error/conflict states

Explicit conflicts:
- duplicate deterministic tax identity;
- stale Party version;
- merge target/source changed during review;
- cross-company role/reference;
- inactive/merged Party selected for new transaction;
- missing legal identity/address required by consuming legal document;
- external verification/provider unavailable.

Provider failure never corrupts local Party master; verification state/error remains observable.
