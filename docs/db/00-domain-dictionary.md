# Database Domain Dictionary — Initial

This file defines shared terms before schema design. It is intentionally incomplete and must grow from accepted module workflows.

## Party
A real or legal business subject that may hold one or more roles such as customer, supplier, architect or B2B account.

Decision still required:
- exact party/role table structure

## Product
A sellable, purchasable, stockable or otherwise managed item. Variant/UOM/barcode behavior is not yet frozen.

## Warehouse
A logical/physical inventory storage facility.

## Location
A position within or associated with a warehouse. Exact hierarchy is not yet frozen.

## Commercial Document
A business document participating in commercial workflow, such as quote, order, dispatch or invoice.

## Ledger
An append-oriented authoritative record of posted business effects.

Planned authoritative ledger families:
- inventory
- account
- cash
- bank

## Projection
Derived read-optimized state that can be rebuilt from authoritative data.

## Snapshot
Historical copy of selected values required to keep a document historically correct after master data changes.

## Reservation
A commitment against available inventory. It is not a physical stock movement.

## Posting
The transition/action that makes a business effect authoritative according to that document's workflow.

## Reversal
A compensating record/action that cancels the effect of a posted record without deleting historical truth.

## Outbox
Durable record of an external/asynchronous event written in the same DB transaction as the business change.

## Idempotency
A guarantee that repeated submission/processing of the same logical request does not create duplicate business effects.

## Rule
Terms not defined by accepted module plans must remain UNKNOWN rather than being guessed into schema.
