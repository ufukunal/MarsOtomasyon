# 05 — Veri Modeli Kataloğu

Bu katalog tablo adı sözleşmesi değil; domain sahipliğini ve temel ilişkileri tanımlar.

## Core
- Company
- Branch
- User
- Role
- Permission
- DocumentSequence
- Tax
- Currency / ExchangeRate
- PostingPeriod
- AuditEntry
- IntegrationSetting

## Cari
- Account
- AccountAddress
- AccountContact
- AccountB2BAccess
- AccountTransaction

Account müşteri, tedarikçi veya her ikisi olabilir. Finans authority `AccountTransaction`dır.

## Ürün/Stok
- Product
- Unit
- Barcode
- Warehouse
- Location
- StockMovement
- StockReservation
- StockTransfer / lines
- StockCount / lines
- ProductImage
- ProductFile
- SupplierProduct relation

V1 core'da lot/seri ve çoklu fiyat listesi yoktur.

## Satış
- Quote / lines
- SalesOrder / lines
- Dispatch / lines
- SalesInvoice / lines
- SalesReturn / lines

Sipariş satırı ordered/dispatched/invoiced/remaining progress bilgisi taşır.

## Alış
- PurchaseOrder / lines
- GoodsReceipt / lines
- SupplierInvoice / lines
- PurchaseReturn / lines

Mal kabul physical stock source'dur.

## Kasa/Banka
- CashAccount
- BankAccount
- TreasuryTransaction
- Collection
- Payment
- Expense
- Transfer
- BankStatementImport / rows
- Reconciliation
- PaymentMethod/PaymentType config

## Çek/Senet
- Instrument
- InstrumentMovement/History
- InstrumentImage

Instrument type: received/issued cheque/note. Status akışı tipe göre ayrılır.

## Üretim/Fason
- BOM / lines
- ProductionOrder
- MaterialIssue / lines
- FinishedGoodsReceipt / lines
- SubcontractOrder
- SubcontractShipment / receipt / discrepancy

## İade/RMA
- ReturnRequest
- ReturnReceipt
- ReturnDecision
- ReturnFinancial/Stock references

## İthalat
- ImportShipment
- Container
- ContainerProduct
- Package/Box
- MaterialLocation
- ImportCost
- CostAllocation
- Compatibility/ProductionInstruction
- LoadingSimulation snapshot

## Commerce/B2B
- Channel
- ChannelCredential
- ExternalProductMapping
- ExternalOrderMapping
- SyncJob/SyncError
- WebhookEvent
- B2BUser mapping

## İletişim
- MessageTemplate
- Notification/Delivery
- ProviderAttempt
- CommunicationAttachment

## Sistem
- OutboxMessage
- IdempotencyRecord
- ImportJob
- ExportJob
- BackupRun
- RestoreRun

## Ortak referans ilkesi
Business event/ledger source bağlantılarında kontrollü `source_type + source_id` kullanılabilir; kritik bütünlük gereken yerde açık FK tercih edilir. Generic polymorphism, domain doğruluğunu kaybettirecek ölçüde yaygınlaştırılmaz.
