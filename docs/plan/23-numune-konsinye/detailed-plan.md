# Sample / Consignment — Detailed Plan

Status: DETAILED PLANNING FROZEN

## V38 product surface
List: Belge, Cari, Ürün, Miktar, Gönderim, Beklenen İade, Satışa Dönen, İade, Kalan, Durum.
Detail tabs: Hareketler, Bilgiler, Kalan, Satışa Dönüş, İade, Timeline.
Actions: Gönder, Satışa Dönüştür, İade Al.

## Ownership
Module owns sample/consignment custody document and outstanding quantity. Inventory owns physical quantity/location/disposition. Sales owns conversion to sale. Finance owns revenue/cost/accounting.

## Records
CustodyDocument, CustodyLine, SendEffectLink, ReturnEffectLink, SaleConversionLink, LossDamageEvidence, ExpectedReturnDate.

## Workflow
DRAFT -> APPROVED -> SENT -> PARTIALLY_RETURNED/PARTIALLY_CONVERTED -> RETURNED | CONVERTED | LOSS_REVIEW -> CLOSED.

## Effects
Sample/consignment send is physical Inventory movement to controlled custody, not automatic revenue. Return reverses custody movement. Sale conversion creates normal Sales authority and consumes eligible custody quantity. ACCOUNT/CASH none directly.

## Rules
Ownership remains company-owned until explicit sale/conversion policy. Cumulative returned + converted + approved loss cannot exceed sent. Lot/Serial preserved. Expected return visible but does not auto-post.

## Permissions/API/UI
custody.read/create/send/return/convert/loss.approve/close.
Routes /api/v1/custody/documents/{id}/send|return|convert.

## Acceptance
Quantity reconciliation, partial return/convert, no premature revenue, lot/serial, duplicate conversion, loss approval and company scope.

## UNKNOWN
Whether non-returnable free samples expense immediately and exact consignment billing trigger require explicit Finance/business policy.
