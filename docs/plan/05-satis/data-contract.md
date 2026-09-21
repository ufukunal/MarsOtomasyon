# Sales Conceptual Data Contract

Status: logical/domain planning only. No SQL schema, column type or index is defined here.

## 1. Ownership boundaries

Sales owns:
- Quote and Quote Revision
- Quote Line
- Sales Order
- Sales Order Line
- source/target commercial links owned by Sales
- Proforma and its source link

Inventory/Warehouse owns:
- Reservation authoritative records
- physical inventory ledger
- warehouse/location/lot/serial movement truth
- Dispatch physical posting implementation may be coordinated with Sales document context, but inventory truth remains Inventory/Warehouse authoritative.

Finance owns:
- account ledger
- collection/cash/bank ledger
- financial credit/refund posting
- settlement/allocation model once SALES-B001 is decided.

Returns/RMA owns:
- return authorization/process
- physical receipt/inspection/disposition workflow
- return-specific state machine.

Foundation owns shared technical primitives:
- audit
- idempotency
- outbox
- actor/company context
- public identity convention.

## 2. Conceptual entities

### Quote
Identity and scope:
- internal identity
- public identity
- company
- branch if business numbering/scope requires it

Relationships:
- customer/party reference
- current/latest revision relation
- source opportunity/project/architect context if supplied by upstream workflow
- target Sales Order conversion links

Classification:
- authoritative commercial document.

### Quote Revision
Purpose:
- preserve each externally/operationally meaningful version.

Contains conceptually:
- revision sequence/identity
- quote reference
- state
- validity period
- commercial terms
- customer/product/price/tax/currency snapshots
- requirement/configuration snapshot where relevant
- actor/timestamps

Rule:
- a new revision does not silently mutate a prior historical revision.

### Quote Line
Contains:
- product/variant/configuration reference
- snapshot identity fields required historically
- UOM
- offered quantity
- unit price
- discounts/tax/currency context after policy is defined

Relations:
- revision
- conversion link(s) to Sales Order lines.

### Sales Order
Relationships:
- customer
- originating Quote revision when applicable
- order lines
- reservations
- dispatches
- invoices
- return/RMA links

Classification:
- authoritative commercial demand document.
- not stock ledger.
- not account ledger.

### Sales Order Line
Authoritative:
- ordered commercial quantity for accepted order revision/state.

Derived from linked authoritative records:
- reserved
- shipped
- invoiced
- returned/credited
- remaining-to-ship
- remaining-to-invoice

Must preserve:
- product/UOM/commercial snapshot
- line state/cancelled remainder
- source Quote line if converted.

### Reservation Reference
Owned by Inventory.
Sales needs a stable relation from:
Sales Order Line → Reservation record(s).

Sales may display reservation state but may not store an independent authoritative reservation total disconnected from Inventory truth.

### Dispatch
Conceptual commercial/shipping document associated with Inventory posting.

Relations:
- customer
- source Sales Order
- dispatch lines
- warehouse
- packages/carrier metadata
- inventory ledger posting reference
- invoice source links
- reversal link where posted dispatch is corrected.

### Dispatch Line
Relations:
- Sales Order Line
- product/UOM snapshot
- posted dispatch quantity
- warehouse/location/lot/serial references where Inventory rules require
- inventory ledger movement reference(s)
- invoice line link(s)
- return line link(s)

### Sales Invoice
Finance-relevant commercial document.

Relations:
- customer
- optional Sales Order source
- optional Dispatch source
- invoice lines
- account-ledger posting reference
- e-document integration state
- reversal/replacement relationship
- collection relation depends on SALES-B001
- return/credit relation

### Sales Invoice Line
Relations:
- source Dispatch Line and/or Sales Order Line according to invoice source mode
- product/UOM/commercial snapshot
- invoice quantity
- money/tax/currency values once SALES-B006 is frozen
- financial posting traceability.

### Collection Link
Finance-owned authoritative event.

Sales may store/reference:
- customer
- collection public reference
- invoice/order context only according to approved SALES-B001 policy.

Sales must not create its own competing collection ledger.

### Sales Return Link
Sales stores source relation to a Returns/RMA record.

Conceptual relations:
- customer
- Sales Order Line
- Dispatch Line
- Sales Invoice Line
- RMA/return record
- physical returned quantity
- financial credited quantity as separately derived/linked concepts.

### Proforma
Informational Sales document.

Relations:
- Quote or Sales Order source
- customer
- source lines
- generated PDF/output history where needed.

It is not authoritative inventory/account/cash truth.

## 3. Authoritative vs derived values

### Authoritative
- Quote revision content/status/history.
- Sales Order line ordered quantity and explicit cancellation/amendment records.
- posted Dispatch line quantities as physical document source.
- Inventory ledger movement generated by dispatch posting.
- posted Sales Invoice line/amount values.
- Account ledger movement generated by invoice posting.
- Finance Collection event and cash/bank/account ledger movements.
- Returns physical/financial records owned by their modules.

### Derived / projection
- order reserved total
- order shipped total
- order invoiced total
- order returned total
- order remaining-to-ship
- order remaining-to-invoice
- invoice collection/paid status if SALES-B001 later adopts allocation
- customer current balance
- product current stock
- dashboards/reports

Derived values may be cached/projected but must remain rebuildable.

## 4. Historical snapshots

Required candidate snapshots at the appropriate accepted freeze/posting point:
- customer legal/trade name
- tax ID/tax office as legally required
- invoice address
- shipping address
- contact/recipient delivery data needed for historical document
- product code/name
- variant/configuration
- requirement snapshot where quote/configurator creates custom specification
- UOM
- quantity
- unit price
- discount data
- tax rate/treatment
- currency
- exchange rate and source/date metadata after SALES-B006
- source document visible number and immutable source identity
- project/architect attribution when supplied.

Snapshot fields are deliberate historical denormalization, not master duplicates used as live authority.

## 5. Source/target relation rules

Every quantity-bearing conversion needs line-level traceability.

Required conceptual uniqueness/safety:
- one posted downstream quantity cannot be counted twice against the same source basis;
- repeated command/provider callback cannot create duplicate source-target link;
- reversal links to the original effect rather than deleting it;
- source and target must share permitted company scope;
- a cancelled/terminal source scope cannot create new downstream quantity.

Exact PK/FK/unique constraints belong to P3.

## 6. Concurrency risks for P3

Must receive durable DB strategy later:
- two users reserve the same remaining availability;
- two users post dispatches against the same order remainder;
- two users invoice the same eligible dispatch/order quantity;
- duplicate invoice Post command;
- duplicate dispatch Post command;
- duplicate external order ingest;
- reversal racing with downstream creation.

Potential mechanisms (not selected here):
- optimistic concurrency/version
- unique constraints
- transactional re-check
- row locking where justified.

P3 selects the minimum correct mechanism based on actual model/access pattern.

## 7. Multi-company / branch / warehouse scope

Company:
- mandatory on Sales transactional authority.

Branch:
- required where numbering, ownership, permission or accounting policy makes branch significant; exact per-entity placement belongs to P3.

Warehouse:
- Reservation and Dispatch physical scope.
- Quote and Invoice do not receive warehouse scope merely because a screen shows warehouse context.

Cross-company source/target links are forbidden.

## 8. Numbering

Human-visible numbers are separate from internal identity.

Required future series:
- Quote
- Sales Order
- Dispatch/İrsaliye
- Sales Invoice
- Proforma

Number assignment/freeze point and branch/period series rules are Settings/Numbering decisions and must not be guessed in PLAN-002.

## 9. Data blockers

- SALES-B001 controls whether Invoice ↔ Collection Allocation becomes a first-class relationship.
- SALES-B002 controls direct invoice relationship to inventory posting.
- SALES-B003 controls conversion quantity/cardinality Quote Line → Sales Order Line.
- SALES-B005 controls financial cost/COGS relation.
- SALES-B006 controls final money/tax/FX value contract.
- SALES-B008 controls amendment/version relation for confirmed orders.
