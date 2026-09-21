# Party / Customer / Supplier Permissions and Scope

Status: FROZEN planning-level permission contract.

## 1. Principles

- authorization is server-side;
- every command validates company scope;
- Party read does not imply sensitive identity read;
- merge/deactivate/reactivate are distinct high-risk master-data actions;
- Finance balances/risk are separately authorized Finance projections;
- UI hiding is not security.

## 2. Proposed permission namespace

Core Party:
- party.read
- party.create
- party.edit
- party.deactivate
- party.reactivate
- party.export

Roles:
- party.role.read
- party.role.manage

Contacts:
- party.contact.read
- party.contact.manage

Addresses:
- party.address.read
- party.address.manage

Tax identities:
- party.tax_identity.read
- party.tax_identity.read_full
- party.tax_identity.manage

Duplicate/merge:
- party.duplicate.review
- party.duplicate.keep_separate
- party.merge

External mappings:
- party.external_mapping.read
- party.external_mapping.manage

Finance projections are not granted by Party permissions alone:
- Finance defines its own balance/risk permissions.

## 3. Company scope

Every Party mutation requires actor access to Party company.

Cross-company:
- read/use is denied unless a future explicit administrative cross-company capability is accepted.
- no Party command silently changes company ownership.
- merge is same-company only.

Branch:
- no default Party identity ownership.
- consuming module may apply branch visibility/use policy later.

## 4. Sensitive tax identity

Full VKN/TCKN/other sensitive identity visibility may be restricted by `party.tax_identity.read_full`.

UI may mask values for ordinary list/detail views while still allowing authorized legal-document workflows to consume the value server-side.

Export must apply the same sensitive-field authorization; export permission alone does not bypass tax-identity permission.

## 5. Create/edit

Create:
- `party.create`;
- company scope;
- duplicate checks.

Edit:
- `party.edit`;
- ACTIVE/INACTIVE rules;
- deterministic duplicate collision validation;
- audit.

Editing live master cannot alter historical document snapshots.

## 6. Role management

`party.role.manage` required to:
- activate CUSTOMER;
- activate SUPPLIER;
- deactivate/reactivate a role.

Role management:
- has no ACCOUNT posting effect;
- cannot create/delete Finance ledger history.

## 7. Deactivate/reactivate

Deactivate:
- `party.deactivate`;
- mandatory reason;
- audit.

Reactivate:
- `party.reactivate`;
- duplicate/identity validation rerun;
- audit.

Neither action reverses/cancels existing transactions.

## 8. Duplicate review / keep separate

Soft duplicate warnings may be accepted as separate Parties only by actor with:
- `party.duplicate.keep_separate`;
- mandatory reason.

A deterministic uniqueness collision is not overridable merely by this permission.

## 9. Merge

Merge requires:
- `party.merge`;
- same-company source/survivor;
- explicit survivor selection;
- conflict review;
- mandatory reason;
- audit.

Merge:
- creates no ledger posting;
- does not silently rewrite historical documents;
- leaves source as MERGED lineage record.

No mandatory second-person approval is invented in PLAN-003; if future SoD policy requires merge approval it must be added explicitly.

## 10. Finance projection access

Party detail may show Finance-derived receivable/payable/risk only if actor also holds the relevant Finance permission.

Parties cannot:
- edit balance;
- edit ledger;
- clear credit hold;
- net customer/supplier positions.

## 11. Audit

State-changing/high-risk events record:
- actor;
- company;
- action;
- Party;
- prior/new state;
- reason where required;
- changed role/identity/address/contact references;
- duplicate/merge survivor-source;
- timestamp/correlation id.

Sensitive values should be handled according to security/logging policy; audit must not become an uncontrolled PII dump.
