# Logical Module / Schema Ownership

Status: FROZEN — PLAN-010.

Logical ownership does not require one PostgreSQL schema per module; physical schema naming remains a later implementation choice.

| Module | Authoritative logical records | Must not own |
|---|---|---|
| Foundation | Company/Branch context references, Mars ERP Permission Grant authority/evaluation, operation idempotency, outbox, approval evidence, audit primitives | domain balances, stock, document policy |
| Parties | Party, Role, Tax Identity, Contact, Communication Point, Address, Merge Lineage, Party External Mapping | receivable/payable balance, credit/risk ledger |
| Product | Product, Variant, UOM/Product-UOM, Barcode/External Mapping, categories/classification | stock balance, average cost |
| Inventory/Warehouse | Warehouse, Location, disposition definitions, Lot, Serial, Reservation, Inventory Ledger, transfer/count/work physical records | Party balance, cash/bank, valuation authority |
| Sales | Quote/revisions, Sales Order/versions/amendments, Dispatch, Sales Invoice commercial/tax snapshots, Sales source links | Reservation truth, inventory balance, Collection settlement |
| Purchasing | Purchase Order/version history, Goods Receipt, Supplier Invoice commercial/match records, Purchasing source links | inventory balance, supplier Payment |
| Finance | Finance Transaction, Account/Cash/Bank ledgers/accounts, Posting Period, FX/carrying/revaluation, Bank Statement/Reconciliation, risk policy, Inventory Valuation/Cost Ledger | physical stock, commercial source history |
| Checks/Notes | Instrument, Instrument Movement, custody/lifecycle/source links | Account/Cash/Bank balances, instrument monetary ledger copy |
| Returns | Return Case/Line, Return Source Link, processing references, source-less evidence, Replacement Link | physical stock ledger, Account/Cash/Bank/value ledgers |
| Read Models | rebuildable projections/materialized read structures | any source-of-truth |

## Cross-module dependency direction

- Sales references Parties/Product; Reservation is created in Inventory from Sales source.
- Dispatch owns commercial shipment state but its POST creates Inventory Ledger effects through accepted cross-module transaction contract.
- Purchasing references Parties/Product; Goods Receipt POST creates Inventory Ledger effects.
- Finance references commercial/physical source identities but never mutates source documents to mark settlement.
- Returns references Sales/Purchasing sources, Inventory movements and Finance transactions; it coordinates without copying their authority.
- Checks/Notes references Party/Bank context and Finance positions; custody remains Checks/Notes authority.
- Foundation primitives support every module but contain no ERP policy.

## Transaction ownership

Logical aggregate ownership and transaction composition are separate:
- an application command may atomically write records owned by more than one module in the same PostgreSQL transaction;
- ownership remains visible through explicit FK/reference contracts;
- no distributed transaction or duplicate mirror ledger is introduced.

## Commercial document strategy

PLAN-010 rejects one universal nullable business-document authority.

Use bounded-context document aggregates with consistent shared conventions:
- internal/public identity;
- company and applicable branch;
- business number;
- state/version;
- actor/timestamps;
- immutable snapshots at accepted freeze/post points;
- source-target links;
- reversal/original references where relevant.

Where future implementation extracts a reusable technical document base, it must not centralize domain-specific state machines or effects.


## P5 authorization amendment

Accepted by ADR-0005:
- ERP permission semantics remain Mars-owned and separate from Identity/OpenIddict authentication/protocol authority;
- Foundation owns the logical Permission Grant authority and evaluation contract;
- initial effective grant grain is Actor + Company + PermissionCode;
- domain modules own concrete permission names such as `party.create`;
- a future role/group administration model may feed the same evaluator without moving ERP authorization authority into the identity provider.
