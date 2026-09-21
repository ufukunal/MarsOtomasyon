# Party / Customer / Supplier UI / Form Contract

Status: FROZEN — PLAN-003

## 1. Personas

Primary:
- Sales user selecting/maintaining customers;
- Purchasing user selecting/maintaining suppliers;
- Finance user reviewing account context;
- master-data administrator resolving duplicates/merges;
- management user viewing relationship summaries.

## 2. Party list

Canonical operational home.

Columns/filters:
- Party Code;
- legal name;
- display/trade name;
- kind PERSON/ORGANIZATION;
- active roles CUSTOMER/SUPPLIER;
- state ACTIVE/INACTIVE/MERGED;
- primary city/country where useful;
- masked tax identity according to permission;
- duplicate-warning indicator;
- last update.

Primary action:
New Party.

Search:
- Party Code;
- legal/display name;
- tax identity when actor may access it;
- phone/email;
- role/state/company scope.

MERGED rows remain findable in history/admin views and visibly redirect to survivor.

## 3. Party create/edit form

Sections:
1. Identity
2. Roles
3. Tax Identities
4. Contacts
5. Addresses
6. Cross-module read context
7. Audit/history

Identity:
- company context fixed/visible;
- kind PERSON/ORGANIZATION;
- legal name;
- display/trade name;
- Party Code.

Roles:
- CUSTOMER toggle/state;
- SUPPLIER toggle/state;
- future roles appear only when their module contracts exist.

Do not create separate Customer/Supplier master forms that duplicate legal identity.

## 4. Tax identity UX

Show:
- country/jurisdiction;
- scheme (e.g. VKN/TCKN);
- value masked by default where appropriate;
- validation/verification status;
- last verified source/time if available.

Turkey helpers:
- VKN expects 10 digits;
- TCKN expects 11 digits.

Legal-document readiness should distinguish:
- Party master structurally valid;
- e-document/legal workflow complete/verified.

Do not claim provider/GİB enrollment merely from a locally valid number.

## 5. Contact UX

Grid/card:
- contact person name;
- role/title if supplied;
- phone/email;
- purpose;
- primary;
- ACTIVE/INACTIVE.

F2/lookup consuming screens should prefer eligible active contacts but allow deliberate selection.

## 6. Address UX

Address list:
- purpose BILLING/SHIPPING/GENERAL;
- label;
- country/city/district/local components;
- primary/default flags by purpose;
- ACTIVE/INACTIVE.

Document UI:
- clearly shows selected live address before freeze;
- after document posting/freeze, displays document snapshot as historical, not silently refreshed current master.

## 7. Role and Finance context

Customer/Supplier role cards display operational state.

Finance panels, if present:
- receivable balance;
- payable balance;
- future credit/risk/hold signal.

They are read-only projections with Finance source label.

Never provide editable current-balance field.
Never present automatic net customer/supplier balance as Party truth.

## 8. Deactivate/reactivate

Deactivate:
- confirmation because it affects new selection;
- mandatory reason;
- show active roles and downstream-use warning;
- does not imply cancellation/reversal of existing documents.

Reactivate:
- privileged action;
- reruns duplicate/identity checks;
- audit visible.

## 9. Duplicate warning

During create/edit:
- deterministic collision appears as blocking conflict;
- fuzzy candidate panel shows likely matches and why they matched;
- user can open existing Party;
- authorized keep-separate action requires reason;
- no auto merge.

## 10. Merge UX

High-risk dedicated workflow, not inline edit.

Show side-by-side:
- survivor/source;
- roles;
- legal/display identity;
- tax identities;
- contacts;
- addresses;
- external mappings;
- history/usage counts where projection exists.

Require:
- survivor explicit selection;
- conflict choices;
- merge reason;
- final confirmation;
- permission.

After merge:
- source displays MERGED → survivor link;
- history remains accessible;
- source cannot be selected in new documents.

## 11. States/errors/accessibility

Distinguish:
- loading;
- no data;
- filter empty;
- no permission;
- duplicate conflict;
- stale edit;
- inactive;
- merged;
- external verification pending/error.

Keyboard:
- F2 lookup compatibility;
- predictable Tab/Enter;
- Escape for safe dialog close;
- visible focus;
- status not color-only.

Mobile:
- task-focused identity/contact/address views;
- do not compress large desktop grids.
