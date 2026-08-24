# 05 — Veri Modeli Kataloğu V4

Bu katalog fiziksel migration'ın birebir sözleşmesi değildir; domain sahipliğini, authority kaynaklarını ve korunacak ana entity ilişkilerini tanımlar.

## 1. Core / Organization
- Company
- Branch
- User / Membership
- Role
- Permission
- DocumentSequence
- Tax
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
- AccountB2BAccess / B2BUser relation
- AccountNote / AccountFile
- authoritative `AccountTransaction`
- rebuildable AccountBalance projection

Account müşteri, tedarikçi veya her ikisi olabilir.

**OpenItem/fatura-allocation/settlement modeli yoktur.**

## 3. Catalog / Product
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
- sale_price
- purchase_price

Lot/serial V1 core schema'sında yoktur.

## 4. Product media destinations
Ürün görselleri yalnız tek galeri değildir. Kullanım yeri/kanal/site ilişkisi taşıyabilir:
- Product Card
- Trendyol
- B2B
- belirli site/domain

Her destination bağımsız:
- main image
- gallery membership
- sort/order
- derived/original metadata

taşıyabilir.

## 5. Warehouse / Inventory
- Warehouse
- Location
- authoritative `StockMovement`
- StockReservation
- StockBalance / Availability projection
- StockTransfer / lines
- StockTransfer movement/receipt lineage
- StockCount / lines
- StockCount adjustment reference

Kullanılabilir stok temel mantığı:
`physical - reserved - quarantine/blocked`.

Quarantine/blocked yalnız gerçek operasyon ihtiyacı oluştuğunda stock state/projection olarak kullanılır; lot/seri anlamına gelmez.

## 6. Commercial documents
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

Belge satırları entered/base quantity, price/tax snapshot ve source lineage taşır. Posted/finalized belge current master değişince silent rewrite edilmez.

## 7. Sales progress
SalesOrderLine en az:
- ordered_qty
- dispatched_qty
- invoiced_qty
- cancelled_qty
- remaining_to_dispatch
- remaining_to_invoice

Kısmi sevk/faturalama first-class'tır.

## 8. Purchasing progress
PurchaseOrderLine en az:
- ordered_qty
- received_qty
- invoiced_qty
- cancelled_qty
- remaining_to_receive
- remaining_to_invoice

GoodsReceiptLine basit kontrol kararı taşıyabilir:
`uygun | kontrol_bekliyor | uygun_degil`.

## 9. Treasury / Finance
- CashAccount
- BankAccount
- TreasuryTransaction / Movement
- Collection
- Payment
- Expense
- Transfer
- PaymentMethod / PaymentType config
- POS / VirtualPOS detail where applicable
- BankStatementImport / rows / matches
- Reconciliation
- CashCount
- CashCountDenomination

Tahsilat/ödeme invoice allocation tablosuna bağlanmaz; AccountTransaction üretir.

## 10. Çek / Senet
- Instrument
- InstrumentMovement / History
- InstrumentPhysicalLocation
- InstrumentImage (front/back)

Received/issued ve cheque/promissory-note ayrımları açık tutulur. Cari effect teslim/posting aşamasında oluşur; later bank collection/payment ikinci cari effect üretmez.

## 11. Basit Üretim
- BOM/Recipe
- RecipeLine
- ProductionOrder
- MaterialIssue / lines
- FinishedGoodsReceipt / lines
- ProductionTechnicalFile
- production progress / scrap-missing metadata

Routing, WorkCenter, ECO, OperationRun, OEE core değildir.

## 12. Fason
- SubcontractOrder
- SubcontractMaterialShipment
- SubcontractReceipt
- SubcontractDiscrepancy / scrap-missing
- remaining custody projection
- notes/files/technical instructions

Ayrı stok authority kurulmaz.

## 13. İade / RMA
- ReturnRequest
- ReturnReceipt
- ReturnDecision
- ReturnStockReference
- ReturnFinancialReference
- source document/line lineage

## 14. İthalat
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

Aynı ürün farklı konteynerlerde bulunabilir; lineage korunur.

## 15. E-Ticaret / B2B
- Channel
- ChannelCredential
- ExternalProductMapping
- ExternalOrderMapping / normalized order snapshot
- external shipment/cancel/return mappings
- IntegrationInbox / WebhookEvent
- SyncJob / SyncError / Problem
- QuestionInbox
- invoice/e-document sync metadata
- B2BUser → Account mapping

External entity identity ile inbound message identity ayrı kavramlardır.

## 16. Communication / Integrations
- ProviderConfig
- MessageTemplate (versioned)
- Notification
- Delivery
- ProviderAttempt
- CommunicationAttachment
- connection-test metadata

Kanallar: SMS, E-Mail, WhatsApp, E-Document, Scanner Agent config.

## 17. Reports
- ready report definitions/config
- SavedReport filter/view state
- ScheduledReport settings
- generated artifact refs if persisted

Generic raw SQL/report designer schema yoktur.

## 18. Files
- Attachment / Media
- DocumentVersion
- posted PDF/XML artifact refs where required
- checksum/security metadata
- scan/quarantine status when used

## 19. Outbox / Inbox
- OutboxMessage
- IntegrationInbox
- IdempotencyRecord

Valkey business truth değildir.

## 20. Read models
- cari balance/running statement
- stock balance/availability
- sales/purchase progress
- cash/bank summary
- cheque/note portfolio
- channel operation summary
- report aggregates

Hepsi authoritative source'tan rebuildable olmalıdır.

## 21. Import / Export / Migration
- ImportJob / row result/manifest
- ExportJob / artifact
- stable source_instance/entity/source_id provenance

Repeated import aynı business kaydı/effect'i duplicate etmez.

## 22. Ortak referans ilkesi
Business event/ledger source bağlantılarında kontrollü `source_type + source_id` kullanılabilir. Kritik bütünlükte explicit FK/unique constraint tercih edilir. Generic polymorphism domain doğruluğunu kaybettirecek ölçüde yaygınlaştırılmaz.
