# 05 — Veri Modeli Kataloğu V4.1

Bu katalog fiziksel migration'ın birebir sözleşmesi değildir; domain sahipliğini, authority kaynaklarını ve korunacak ana entity ilişkilerini tanımlar.

## 1. Core / Organization
- Company
- Branch
- User / Membership
- Role
- Permission
- DocumentSequence
- Tax / TaxZeroReason
- Currency / ExchangeRate
- PostingPeriod
- AuditEntry
- IntegrationSetting
- IdempotencyRecord
- BackupRun
- RestoreRun

Company tenant/hukuki sınırdır; Branch operasyon birimidir.

## 2. Cari / Accounts
- Account
- AccountAddress
- AccountContact / AuthorizedContact
- AccountBankInfo
- AccountB2BAccess
- AccountNote / AccountFile
- authoritative `AccountTransaction`
- rebuildable AccountBalance projection

Account müşteri, tedarikçi, karma veya marketplace clearing amaçlı olabilir. V1 Account tek `book_currency` taşır.

**OpenItem/fatura-allocation/settlement modeli yoktur.**

## 3. B2B identity
External B2B kullanıcı internal User değildir:
- B2BUser
- B2BAccountAccess / Account relation
- B2BRole/Permission veya typed permission set
- activation/password/session/token metadata

B2BUser tam olarak bir/izin verilen Account bağlamında çalışır; internal admin RBAC privilege taşımaz.

## 4. Catalog / Product
- Product
- Category
- Brand (gerçek kullanım varsa)
- Unit
- UnitConversion (gerçek çoklu birim ihtiyacında)
- Barcode
- Package/ProductRelation (operasyonel ihtiyaçta)
- ProductSupplier relation
- ProductTechnicalFile
- ProductInstallationGuide
- ProductImage
- ProductFile

Core fiyat:
- sale_price_net
- purchase_price_net

Lot/serial V1 core schema'sında yoktur.

## 5. Product media destinations
Ürün görselleri yalnız tek galeri değildir. Kullanım yeri/kanal/site ilişkisi taşıyabilir:
- Product Card
- WooCommerce/site
- aktif marketplace provider/account
- B2B
- belirli site/domain

Her destination bağımsız:
- main image
- gallery membership
- sort/order
- derived/original metadata
- provider validation/publish metadata

taşıyabilir.

## 6. Warehouse / Inventory
- Warehouse
- Location
- authoritative `StockMovement`
- StockReservation
- StockBalance / Availability projection
- StockTransfer / lines
- StockTransfer movement/receipt lineage
- InTransitStock/Custody projection
- StockCount / lines
- StockCount adjustment reference

Kullanılabilir stok:
`physical - reserved - quarantine/blocked`.

Quarantine/blocked lot/seri anlamına gelmez. Transfer issue sonrası destination receipt'e kadar quantity/value in-transit custody'de izlenir.

## 7. Commercial documents
Header + lines:
- Quote + revisions
- SalesOrder
- Dispatch/Shipment
- SalesInvoice
- SalesReturn
- PurchaseOrder
- GoodsReceipt
- SupplierInvoice
- PurchaseReturn
- Proforma (non-ledger document)

Belge satırları entered/base quantity, net price/tax/discount snapshot, entered price mode ve source lineage taşır. Posted/finalized belge current master değişince silent rewrite edilmez.

## 8. Sales progress
SalesOrderLine en az:
- ordered_qty
- dispatched_qty
- reversed_dispatch_qty
- invoiced_qty
- reversed_invoice_qty
- returned_qty
- reversed_return_qty
- cancelled_qty
- remaining_to_dispatch
- remaining_to_invoice

Kısmi işlem ve reversal-safe progress first-class'tır.

## 9. Purchasing progress / quality split
PurchaseOrderLine en az:
- ordered_qty
- accepted_qty
- invoiced_qty
- cancelled_qty
- remaining_to_receive
- remaining_to_invoice

GoodsReceiptLine en az:
- physical_received_qty
- accepted_qty
- pending_quality_qty
- rejected_qty
ve ilgili quality/custody metadata taşıyabilir.

## 10. Treasury / Finance
Authority:
- immutable/appended `TreasuryMovement`

Source/operational kayıtlar:
- CashAccount
- BankAccount
- Collection
- Payment
- Expense
- Transfer
- PaymentMethod / PaymentType config
- POS / VirtualPOS transaction
- POSSettlement
- BankStatementImport / rows / matches
- Reconciliation
- CashCount
- CashCountDenomination

Cash/bank/POS balance `TreasuryMovement` üzerinden rebuild edilebilir. Tahsilat/ödeme invoice allocation tablosuna bağlanmaz; AccountTransaction + TreasuryMovement üretir.

## 11. Çek / Senet
- Instrument
- InstrumentMovement / History
- InstrumentPhysicalLocation / Holder
- InstrumentImage (front/back)
- InstrumentAccountEffectReference

Received/issued ve cheque/promissory-note ayrımları açık tutulur. Ciro edilen alınan instrument supplier cari effect reference taşıyabilir. Later bank collection/payment aynı cari effect'i tekrar üretmez.

## 12. Basit Üretim
- BOM/Recipe
- RecipeLine
- ProductionOrder
- MaterialIssue / lines
- FinishedGoodsReceipt / lines
- ProductionTechnicalFile
- production progress / scrap-missing metadata

Routing, WorkCenter, ECO, OperationRun, OEE core değildir.

## 13. Fason
- SubcontractOrder
- SubcontractMaterialShipment
- SubcontractReceipt
- SubcontractDiscrepancy / scrap-missing
- SubcontractCustody / remaining projection
- notes/files/technical instructions

Custody quantity + carrying value korunur. Ayrı stok authority kurulmaz.

## 14. İade / RMA
- ReturnRequest
- ReturnReceipt
- ReturnDecision
- ReturnStockReference
- ReturnFinancialReference
- source document/line lineage

Provider-specific return IDs ayrı external mapping'dir.

## 15. İthalat
- ImportShipment
- Container
- ContainerProduct
- Package / Box
- MaterialLocation
- ImportCost
- CostAllocation
- Compatibility / ProductionInstruction
- TechnicalPhoto/File
- LoadingSimulation snapshot
- ImportReceipt handoff reference where needed

Aynı ürün farklı konteynerlerde bulunabilir; lineage korunur. ImportShipment kendi başına stock authority değildir.

## 16. E-Ticaret / Marketplace / B2B
- Channel
- ChannelCredential
- ChannelCapability
- ProviderRegistryEntry / compatibility metadata
- ExternalProductMapping
- ExternalOrderMapping / normalized order snapshot
- ExternalCustomerSnapshot
- external shipment/cancel/return mappings
- IntegrationInbox / WebhookEvent
- SyncJob / SyncError / Problem
- QuestionInbox
- invoice/e-document sync metadata
- B2BUser → Account mapping

External entity identity ile inbound message identity ayrı kavramlardır.

## 17. Marketplace finance
Marketplace legal customer ile financial counterparty ayrıdır.

Entity adayları:
- MarketplaceClearingAccount relation → Account
- MarketplaceSettlement
- MarketplaceSettlementLine
- MarketplacePayout
- MarketplaceFee / Adjustment
- MarketplaceSettlementExternalIdentity

Invoice clearing receivable yaratabilir; payout/fee/chargeback/refund etkileri AccountTransaction ve/veya TreasuryMovement source references üretir. Provider settlement evidence immutable snapshot/reference olarak saklanır.

## 18. Communication / Integrations
- ProviderConfig
- MessageTemplate (versioned)
- Notification
- Delivery
- ProviderAttempt
- CommunicationAttachment
- connection-test metadata

Kanallar: SMS, E-Mail, WhatsApp, E-Document, Scanner Agent config.

## 19. Reports
- ready report definitions/config
- SavedReport filter/view state
- ScheduledReport settings
- generated artifact refs if persisted

Generic raw SQL/report designer schema yoktur.

## 20. Files
- Attachment / Media
- DocumentVersion
- posted PDF/XML artifact refs where required
- checksum/security metadata
- scan/quarantine status when used

## 21. Outbox / Inbox
- OutboxMessage
- IntegrationInbox
- IdempotencyRecord

Valkey business truth değildir.

## 22. Read models
- cari balance/running statement
- stock balance/availability/in-transit
- sales/purchase progress
- cash/bank/POS summary
- cheque/note portfolio
- marketplace clearing/settlement summary
- channel operation summary
- report aggregates

Hepsi authoritative source'tan rebuildable olmalıdır.

## 23. Import / Export / Migration
- ImportJob / row result/manifest
- ExportJob / artifact
- stable source_instance/entity/source_id provenance

Repeated import aynı business kaydı/effect'i duplicate etmez.

## 24. Ortak referans ilkesi
Business event/ledger source bağlantılarında kontrollü `source_type + source_id` kullanılabilir. Kritik bütünlükte explicit FK/unique constraint tercih edilir. Generic polymorphism domain doğruluğunu kaybettirecek ölçüde yaygınlaştırılmaz.
