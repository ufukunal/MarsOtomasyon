# Party / Customer / Supplier Reporting Contract

Status: FROZEN PLAN-003 planning semantics.

Party reports are read models/projections and never become master or Finance authority.

## 1. Party Directory

Grain:
one Party per row within authorized company scope.

Fields may include:
- Party Code;
- legal/display name;
- kind;
- active roles;
- state;
- primary city/country;
- masked tax identity;
- primary contact;
- duplicate warning;
- merge survivor reference.

Filters:
- company;
- role;
- state;
- kind;
- country/city;
- updated date.

Source:
Parties authoritative master + rebuildable projection.

## 2. Customer / Supplier role views

Customer view:
- Parties with CUSTOMER role.
- role state.
- current identity/contact/address.
- optional authorized Finance receivable/risk projections.

Supplier view:
- Parties with SUPPLIER role.
- role state.
- current identity/contact/address.
- optional authorized Finance payable projections.

Same Party may appear in both views without becoming two master records.

## 3. Finance context

Balances:
- source Finance account ledger/read projection.
- not recomputed from Party.
- receivable and payable remain separate unless Finance later defines offset/netting.

Forbidden report semantics:
- Party.current_balance as master truth.
- automatic net balance of Customer and Supplier roles.
- invoice paid/open status derived by Parties.

## 4. Data-quality report

Operational control view may include:
- missing legal name;
- missing address needed by configured workflow;
- missing/invalid structural tax identity where a legal workflow requires it;
- duplicate candidates;
- inactive primary contact/address;
- external mapping conflicts.

This is a data-quality queue, not legal verification authority.

## 5. Duplicate / merge review report

Grain:
candidate pair or merge lineage.

Show:
- matching reasons;
- deterministic vs fuzzy signal;
- company;
- roles;
- state;
- review outcome;
- merge survivor/source;
- actor/time/reason.

Do not expose an opaque score as automatic truth.

## 6. Inactive / merged Parties

Report:
- state;
- state-change date/actor;
- active roles before state change;
- survivor for MERGED;
- historical usage counts only when sourced from consuming modules.

## 7. KPI policy

PLAN-003 does not freeze management KPIs such as customer count growth, churn, DSO or supplier concentration because formula/grain/status definitions belong Reporting/Finance planning.

Simple operational counts may be displayed only when their grain/filter is explicit.

## 8. Access

Reports honor:
- company scope;
- Party permissions;
- sensitive tax identity permission;
- Finance permissions for balances/risk.

Export applies the same field-level authorization.
