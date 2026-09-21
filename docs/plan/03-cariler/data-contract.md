# Party / Customer / Supplier Conceptual Data Contract

Status: FROZEN logical/domain planning only. No SQL table/column/index is defined here.

## 1. Authority model

Parties authoritative:
- Party live master identity;
- Party kind and lifecycle;
- company-scoped Party Code;
- Party roles and role state;
- live legal/display identity;
- tax identity master records;
- live contacts/communication points;
- live addresses;
- duplicate/merge lineage;
- external identity mappings owned by Party integration context.

Finance authoritative:
- account ledger;
- receivable/payable balances;
- Collection/Payment;
- credit/risk/hold;
- settlement/netting.

Consuming documents authoritative for:
- historical Party snapshot values captured at their accepted freeze/post point.

Derived/projection:
- Party search index;
- duplicate candidates;
- Finance balance/risk panels;
- document counts/last activity;
- reports.

## 2. Conceptual entities

### Party

Meaning:
one company-scoped real/legal business subject.

Conceptual attributes:
- identity;
- company scope;
- Party Code;
- kind PERSON / ORGANIZATION;
- legal identity values;
- display/trade identity;
- state ACTIVE / INACTIVE / MERGED;
- merge-survivor lineage when applicable;
- audit/concurrency metadata.

Party does not contain authoritative account balance or stock.

### Party Role

Purpose:
qualify how one Party participates in business.

Required PLAN-003 role types:
- CUSTOMER;
- SUPPLIER.

Future role candidates from shared domain dictionary:
- ARCHITECT;
- B2B account context.

Rules:
- many roles may belong to one Party;
- role state is independent ACTIVE/INACTIVE;
- role does not duplicate Party legal identity;
- role-specific defaults/references are not ledger authority.

### Tax Identity

Conceptual relation:
Party 1 → many Tax Identities.

Contains:
- jurisdiction/country;
- scheme/type;
- value;
- current/active state;
- verification metadata where an authoritative verification exists.

Turkey schemes:
- VKN: 10 digits;
- TCKN: 11 digits.

Exact checksum/provider validation is implementation-time official-rule logic, not a second Party identity source.

### Contact Person

Operational person associated with a Party.

Contains conceptually:
- name;
- title/role label where supplied;
- active state;
- communication points;
- purpose.

It is not automatically a Party.

### Communication Point

Examples:
- phone;
- email;
- other future accepted channel identifier.

Contains:
- type;
- value;
- purpose;
- primary flag;
- active state.

Communications consent/channel-preference authority is outside PLAN-003 unless later source-backed.

### Address

Party 1 → many Addresses.

Contains conceptually:
- country/jurisdiction;
- structured address parts;
- purpose;
- label;
- active state;
- default-by-purpose relation where adopted.

Purposes:
- BILLING;
- SHIPPING;
- GENERAL/OTHER.

Address master is current operational truth; historical document address is a snapshot.

### Party External Mapping

Purpose:
map a Party to provider/external-system identities without making provider ID the Party primary identity.

Contains conceptually:
- source/provider/system;
- external identity;
- scope/account where required;
- active state;
- audit.

### Party Merge Lineage

Purpose:
retain logical consolidation history.

Relations:
- merged source Party;
- survivor Party;
- merge reason;
- actor/time;
- conflict-resolution evidence.

Source Party remains historically addressable and state = MERGED.

## 3. Cardinality and uniqueness intent

- Company 1 → many Parties.
- Party 1 → many Roles.
- Party 1 → many Tax Identities.
- Party 1 → many Contacts.
- Party 1 → many Addresses.
- Party 1 → many External Mappings.
- Party may be source of at most one active merge-survivor lineage after MERGED.

Future P3 constraints must make deterministic identity collisions durable.

Intent:
- within a company, one normalized active tax identity scheme/value must not silently identify two active Parties.
- Party Code is unique within its company numbering scope.
- provider external mapping uniqueness is scoped to provider/account/system context.

Exact normalization and index strategy belong P3.

## 4. Company and branch scope

Party:
- company-scoped authoritative master.

Role/contact/address/tax identity:
- inherit Party company authority conceptually; do not create independent cross-company truth.

Branch:
- not identity scope by default.
- branch restrictions/defaults belong permissions/consuming workflows when a concrete requirement exists.

Cross-company Party references are forbidden.

## 5. Live master vs snapshot

Live Party master can change.

Historical document snapshot is deliberate denormalization and may contain:
- Party Code visible at document time;
- legal name;
- display/trade name where printed;
- VKN/TCKN/other legal identity as applicable;
- invoice/billing address;
- shipping address;
- recipient/contact;
- country/jurisdiction-relevant identity fields.

Rules:
- master edit never updates posted/frozen snapshot;
- Party deactivate/merge never rewrites historical snapshot;
- document keeps source Party reference/lineage for audit/navigation.

## 6. Duplicate model

Hard/deterministic candidate:
- tax identity uniqueness within company and scheme where legally applicable;
- Party Code uniqueness;
- external mapping uniqueness within its source scope.

Soft candidates:
- legal/display name similarity;
- email/phone overlap;
- address similarity.

Soft candidate is projection/review input, not authority.

No fuzzy score becomes a database truth or automatic merge instruction.

## 7. Merge model

Merge is logical consolidation, not hard delete.

Survivor:
- remains ACTIVE/INACTIVE according to explicit action;
- receives/links accepted non-conflicting current master data.

Source:
- state = MERGED;
- records survivor lineage;
- unavailable for new selection;
- remains historical/audit identity.

Transactions:
- are not mass-rewritten blindly in PLAN-003;
- historical snapshots stay unchanged;
- navigation/projections may resolve merge lineage.

No merge operation alters Finance ledger amounts or nets balances.

## 8. Finance relationship

Conceptual relation:
Party/company → Finance account context/ledger references.

Rules:
- balance is derived from Finance ledger.
- Parties may query authorized receivable/payable projections.
- a dual-role Party can have both receivable and payable activity.
- no automatic netting/offset is assumed.
- credit/risk/hold is read-only Finance authority.

## 9. Defaults and policy references

Optional Party/Role-level defaults may reference:
- currency preference;
- payment-term preference;
- future accepted commercial policies.

Rules:
- default is suggestion for new documents.
- consuming module/policy owns precedence and exception logic.
- posted document snapshots its accepted value.
- absence of default does not make Party invalid.

## 10. Concurrency risks for P3

Future durable strategy required for:
- duplicate Party creation using same tax identity;
- concurrent Party Code assignment;
- stale edit of identity/address/contact;
- simultaneous merge and edit;
- simultaneous merge of same source to different survivors;
- role activation/deactivation racing with document creation;
- external mapping duplicate ingestion.

Potential mechanisms are decided in P3 based on actual schema/access patterns; PLAN-003 does not choose physical locking/index syntax.

## 11. Forbidden models

- separate Customer and Supplier master identities for the same Party merely because of role;
- authoritative mutable customer/supplier balance on Party;
- comma-separated contacts/addresses/roles;
- JSON/EAV escape for normal relations;
- hard delete of merged/historically referenced identity to erase history;
- provider ID as canonical Party primary identity;
- cache/search index as master truth.
