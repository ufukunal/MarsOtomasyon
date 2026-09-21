# Party / Customer / Supplier Domain Plan

Status: COMPLETED / FROZEN — PLAN-003

## 1. Business objective

Maintain one reliable company-scoped counterparty identity that Sales, Purchasing, Finance and later modules can reference without duplicating Customer/Supplier master truth, while preserving historical documents when the live master changes.

Expected value:
- eliminate duplicate Customer vs Supplier cards for the same subject;
- make current legal/contact/address data maintainable in one place;
- keep company/account/role boundaries explicit;
- prevent balance/credit logic from leaking into master data;
- support safe deactivation and duplicate consolidation without deleting history.

## 2. Scope

In:
- Party PERSON / ORGANIZATION classification;
- CUSTOMER and SUPPLIER roles;
- multiple simultaneous roles;
- legal/display names;
- tax identities including VKN/TCKN schemes;
- contact people and communication points;
- billing/shipping/general addresses;
- company scope;
- Party/role codes and numbering ownership;
- active/inactive/merged lifecycle;
- duplicate detection and merge lineage;
- historical snapshot boundary;
- Sales/Purchasing/Finance relationships;
- currency/payment-term default ownership boundary;
- credit/risk ownership boundary;
- permissions/integrations/reporting planning.

Out:
- SQL/migrations;
- API/UI implementation;
- account-ledger schema;
- credit-limit/risk scoring formula;
- settlement/netting;
- tax checksum algorithms/provider enrollment;
- exact numbering format;
- Purchasing workflow;
- CRM/files/tags.

## 3. Source decisions

Repository sources:
- `docs/db/00-domain-dictionary.md`: Party may hold one or more roles such as customer/supplier.
- `docs/db/01-design-principles.md`: 3NF, no authoritative mutable balances, historical snapshots allowed.
- frozen Sales contracts under `docs/plan/05-satis/`: customer snapshots are immutable; balance/Collection belong Finance; cross-company scope explicit.
- Foundation: module ownership, server authorization, numbering abstraction, audit/outbox/idempotency.

External legal/product verification:
- GİB guidance distinguishes 10-digit VKN and 11-digit TCKN.
- GİB e-Fatura guidance requires recipient VKN/TCKN and corresponding legal name/name-surname plus required address data for invoice use.
These rules are used only for Turkish legal-document identity planning; current official rules must be reverified at implementation time.

## 4. Frozen domain decisions

### PARTY-D001 — Single Party identity with multi-role model

- Party is the authoritative live master identity.
- Party kind: PERSON or ORGANIZATION.
- CUSTOMER and SUPPLIER are roles attached to Party; a Party may have both.
- Future roles such as ARCHITECT/B2B may reuse the same Party core when their own module contract is accepted.
- A role cannot create a second legal identity record.

### PARTY-D002 — Company scope

- Party is company-scoped.
- Cross-company documents cannot reference another company's Party.
- If the same real-world subject trades with another Mars company, that company maintains its own Party master.
- No automatic cross-company merge/share is introduced in PLAN-003.
- Branch is not part of Party identity by default; branch-specific use belongs consuming workflow/permissions when needed.

Rationale:
company is the authoritative commercial/accounting boundary already required by Sales/Finance planning; this avoids accidental tenant/company data leakage.

### PARTY-D003 — Legal vs display identity

ORGANIZATION:
- legal_name is the legal-document identity source.
- display/trade name may differ for search/UI but cannot replace legal_name on legal snapshots where legal identity is required.

PERSON:
- legal person name is the legal-document identity source.
- display name may be optimized for UI/search but does not rewrite legal snapshot meaning.

Historical documents snapshot the selected legal/display/tax/address values required by that document.

### PARTY-D004 — Tax identities

Conceptual tax identity:
- Party;
- jurisdiction/country;
- scheme/type;
- value;
- verification/status metadata where available.

Turkey:
- VKN scheme: 10 digits.
- TCKN scheme: 11 digits.
- PERSON/ORGANIZATION classification guides valid legal-document identity usage, but PLAN-003 does not hard-code every jurisdiction's tax law.
- a Party may exist without Turkish VKN/TCKN when legal-document workflow does not require it.
- e-Fatura/e-Arşiv generation must validate required recipient identity/address fields through the applicable integration/legal rules before send.

The Party master is not the authority for e-document enrollment status unless a future integration contract explicitly makes a verified provider/GİB lookup projection available.

### PARTY-D005 — Party code and role references

- one canonical human-visible `Party Code` per company Party.
- Customer/Supplier roles do not require duplicate master codes.
- role-specific external/reference codes may exist as aliases/mappings but cannot become alternate authoritative identity.
- exact Party Code format/series is owned by Settings/Numbering; PLAN-003 requires only concurrency-safe, immutable-after-assignment semantics once used.
- internal/public IDs follow Foundation identity conventions later in schema design.

### PARTY-D006 — Contacts

Party may have multiple:
- contact people;
- phone/email/communication points;
- purpose labels and primary flags.

Rules:
- Contact Person is operational contact data, not automatically a separate Party.
- if a contact person later becomes an independent commercial subject, a separate Party may be created/linked explicitly; no silent conversion.
- obsolete contacts become inactive/history-visible when referenced; they are not silently rewritten into historical documents.
- exact communication consent/marketing policy belongs Communications/Privacy planning, not PLAN-003.

### PARTY-D007 — Addresses

Party may have multiple addresses with explicit purpose:
- BILLING;
- SHIPPING;
- GENERAL/OTHER where needed.

Rules:
- current master address may be edited/versioned for future use.
- an address used in a posted/legal document is represented by the document snapshot; changing the master never changes history.
- address may be active/inactive.
- default billing/shipping selection is company-Party context, not historical authority.
- consuming document validates required address completeness for its legal/operational purpose.

### PARTY-D008 — Lifecycle

Party states:
- ACTIVE;
- INACTIVE;
- MERGED.

ACTIVE:
- eligible for new role-based selection subject to role state and permissions.

INACTIVE:
- unavailable for new counterparty selection by default;
- existing documents/history remain readable;
- consuming modules own whether an already-created draft can continue;
- privileged reactivation is allowed with audit.

MERGED:
- terminal for new selection;
- redirects search/navigation to survivor Party;
- original record and lineage remain readable;
- no hard delete of historical master identity.

Role state:
- ACTIVE / INACTIVE independent of Party state.
- disabling CUSTOMER need not disable SUPPLIER role.
- Party INACTIVE effectively disables new use of all roles without deleting them.

### PARTY-D009 — Duplicate detection

Deterministic collision:
- within one company, the same normalized verified tax-identity scheme/value cannot silently create competing active Party identities once P3 supplies the appropriate constraint.

Candidate duplicate warnings:
- same/similar legal name;
- phone/email overlap;
- same/similar address;
- external reference overlap.

Rules:
- fuzzy/name/contact match is warning only.
- system never auto-merges from fuzzy score.
- create flow may continue after warning only with explicit permission/reason if deterministic identity collision is absent.
- exact normalization/DB constraints belong P3.

### PARTY-D010 — Merge policy

Merge is explicit, audited logical consolidation:
1. user selects survivor and duplicate source;
2. system presents role/contact/address/tax/external-reference conflicts;
3. deterministic identity conflicts must be resolved before commit;
4. accepted unique master data moves/links to survivor conceptually;
5. source Party becomes MERGED and records survivor lineage;
6. source Party cannot be selected for new transactions;
7. historical document snapshots stay untouched;
8. existing transactional history remains traceable to original Party identity/merge lineage; no blind historical rewrite.

Merge requires a distinct high-risk permission and mandatory reason.
No automatic merge.

### PARTY-D011 — Finance boundary

Finance owns:
- authoritative account ledger;
- receivable/payable balances;
- Collection/Payment;
- credit exposure;
- credit limit/risk/hold policy;
- settlement/netting policy.

Parties may expose derived read-only Finance projections for an authorized actor.

Forbidden:
- Party.current_balance authority;
- Customer.current_balance authority;
- Supplier.current_balance authority;
- Party master changing ledger history.

A Party with both CUSTOMER and SUPPLIER roles does not imply automatic receivable/payable netting. Any netting/offset is a future Finance decision.

### PARTY-D012 — Currency/payment-term boundary

- Party/role may reference optional default commercial preferences only when the owning Settings/Sales/Purchasing policy defines them.
- defaults are suggestions for new documents, never financial authority.
- accepted/posted documents snapshot their own currency/payment-term values.
- exact catalog, precedence and exception approval remain owned by Settings + consuming commercial module.
- absence of Party defaults does not block Party creation.

### PARTY-D013 — Historical snapshot boundary

Live Party master is normalized; documents deliberately snapshot historical values.

Candidate document snapshots as applicable:
- legal name;
- display/trade name where shown;
- tax identity type/value;
- billing/shipping address;
- recipient/contact;
- Party Code/external visible reference.

After document freeze/post:
- Party edit, deactivate or merge never changes snapshot.
- document keeps original snapshot and original Party reference/lineage.

## 5. Transaction/effect matrix

Party master actions have no RES/STOCK/ACCOUNT/CASH-BANK/COST posting effect.

| Action | DOC/master | RES | STOCK | ACCOUNT | CASH/BANK | COST | Audit |
|---|---|---|---|---|---|---|---|
| Create Party | master create | NONE | NONE | NONE | NONE | NONE | required |
| Edit live Party | master update/version context | NONE | NONE | NONE | NONE | NONE | required |
| Add/remove role | role state | NONE | NONE | NONE | NONE | NONE | required |
| Add/edit contact/address/tax identity | master child state | NONE | NONE | NONE | NONE | NONE | required |
| Deactivate/reactivate | master state | NONE | NONE | NONE | NONE | NONE | required |
| Merge | survivor/source lineage | NONE | NONE | NONE | NONE | NONE | mandatory reason/audit |
| Read Finance balance | projection only | NONE | NONE | NONE | NONE | NONE | read authorization |

## 6. Cross-module contracts

Sales:
- consumes ACTIVE Customer role/current Party data for new documents.
- freezes historical customer snapshots.
- does not own Party master or balance.

Purchasing:
- will consume Supplier role.
- cannot duplicate supplier identity master.
- exact purchasing defaults remain PLAN-005.

Finance:
- references Party/company context for ledger postings.
- owns balances/risk/settlement.
- Parties only reads authorized projections.

Communications:
- may consume contacts later.
- consent/channel-preference policy is not invented here.

## 7. Non-material deferred details

These are explicitly delegated and do not block PLAN-003 core freeze:
- exact Party Code string/series format → Settings/Numbering.
- VKN/TCKN checksum and e-document enrollment validation → current official integration/legal rules.
- credit-limit/risk formula and hold transitions → Finance planning.
- receivable/payable netting → Finance planning.
- payment-term/currency catalog precedence → Settings + Sales/Purchasing.
- CRM notes/tags/files → later source-backed module requirement.

## 8. Reviewer outcome

ERP: one Party + roles preserves commercial identity without duplicate Customer/Supplier masters.
Accounting: all balance/risk/settlement authority stays Finance-owned.
Database: conceptual model is normalized; duplicate/merge/snapshot boundaries are feasible without schema-first assumptions.
Architecture: Parties owns identity, not Sales/Purchasing/Finance posting.
Developer: workflows expose deterministic states/conflicts without requiring physical schema decisions.
Testing: duplicate, merge, inactive, snapshot and company-isolation invariants are testable.
MBA: duplicate prevention is strong without forcing fuzzy auto-merge or universal approval.
UX: current/live identity, role, address and merge state can be presented separately from historical snapshots.
