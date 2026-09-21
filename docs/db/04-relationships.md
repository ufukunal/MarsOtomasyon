# Logical Relationships and Source-Target Model

Status: FROZEN — PLAN-010.

## Party / master
- Company 1→N Party.
- Party 1→N Party Role, Tax Identity, Contact, Address, External Mapping.
- Party Merge Lineage references one merged source and one survivor within the same company.
- Product/Variant/UOM/Barcode/Warehouse/Location relations inherit company compatibility.

## Product / tracking
- Product 1→0..N Variant.
- Product 1→1 Base Product-UOM and 0..N alternate Product-UOM assignments.
- Product/Variant 1→N Lot/Serial identities as tracking strategy allows.
- Warehouse 1→N Location.
- Serial current position is derived from Inventory movements, not a mutable canonical location column.

## Inventory
Reservation references exact Sales Order line/version source.
Inventory Movement references exact owning source document/work line and optional original movement for reversal.
Transfer issue/receive/loss records reference Transfer Line and Inventory movements.
Count adjustment references Count Session/Line and resultant Inventory movement.
Disposition/location moves create explicit source and target physical dimensions with net-zero company quantity when appropriate.

## Sales lineage
- Quote Revision Line → Quote Conversion Link → Sales Order Version/Line.
- Sales Order Version/Line → Reservation(s).
- Sales Order Version/Line → Dispatch Line(s).
- Dispatch Line or eligible Order Line → Sales Invoice Line through exact quantity-bearing source relation.
- Posted Dispatch → Inventory movement(s).
- Posted Sales Invoice → Finance Account Ledger/COGS/value references.
- Original posted effect → linked reversal/compensation.

Cumulative converted/shipped/invoiced quantities are derived from net active source-target records/effects.

## Purchasing lineage
- Purchase Order Line → Goods Receipt Line(s).
- Goods Receipt Line → Inventory movement(s).
- Goods Receipt/PO Line → Supplier Invoice Line match/source relations.
- Supplier Invoice → Finance payable/value effects.
- Purchase Return uses Return Source Link → Goods Receipt lineage and separate Finance context.

No Goods Receipt creates payable; no Supplier Invoice creates physical stock.

## Returns lineage
Return Case 1→N Return Line.
Return Line 1→N Return Source Link.
Customer normal physical source → posted Sales Dispatch line/movement.
Customer financial context → posted Sales Invoice/correction where applicable.
Supplier normal physical source → posted Goods Receipt line/movement.
Supplier financial context → Supplier Invoice/correction where applicable.
Return Line → N Physical Processing Reference → Inventory Movement.
Return Case/Line → N Financial Processing Reference → Finance Transaction/Valuation effects.
Return → Replacement Link → normal Sales document.
Source-less exception stores no fabricated source relation.

Physical and financial remaining values are independently derived.

## Finance lineage
Finance Transaction 1→N Account/Cash/Bank/Valuation effects as required by transaction type.
Account Ledger references Party + Financial Role + source transaction/document.
Cash/Bank Ledger references owning account + Finance Transaction.
Valuation Entry references Product/Variant valuation pool + physical/commercial source.
Dispatch Cost Bridge relates Dispatch cost-out portions to later Sales Invoice COGS consumption.
Late-cost allocation relates Goods Receipt cost basis to on-hand/bridge/recognized COGS portions.

No Finance relation marks an Invoice paid/open.

## Bank reconciliation
Statement Batch 1→N Statement Line.
Reconciliation Match has explicit Match Detail(s) linking Statement Line portions and Bank Ledger Entry portions.
Many-to-many matching is supported through normalized match details and amounts.
Matched total cannot exceed either side's eligible remainder.

## Checks / Notes
Instrument 1→N Instrument Movement.
Movement may reference Finance instrument monetary position/effect and custody target context.
Bank handoff changes custody only.
Settlement movements reference Finance Cash/Bank effects.
Endorsement references target Supplier Party and Finance supplier adjustment.
Original movement → reversal/compensation movement.

## Reversal
Reversal relation is explicit at the owning authoritative record/effect level.
A generic audit row is never used as the only reversal link.
Dependencies may block simple reversal and require a compensation chain.
