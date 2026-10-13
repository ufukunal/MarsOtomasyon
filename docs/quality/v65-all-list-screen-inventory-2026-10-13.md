# v65 tüm liste envanteri — ekran bazlı kaynak değişim ve QA durumu

**Güncel kaynak HEAD:** `a08f7268ab0cc6590d40b16a314228ec6be3d0e1`  
**Baz HEAD:** `04bdfa37964648bcd0d86bceac82c4f47a229b1c`  
**Kaynak:** GitHub `main`. **Test ve tarayıcı QA çalıştırılmadı; Fresh/PROD yayını yapılmadı.**

## 1. Ana rotalar — 28 ekran

Her satır gerçek uygulama sınıfına bağlıdır. *Kod işlendi* kaynak değişimini, *QA bekliyor* ise testin yürütülmediğini gösterir.

| Route | Ekran | Uygulama sınıfı | Kod aşaması | Test / tarayıcı |
|---|---|---|---|---|
| `products.index` | Ürün Listesi | `app/Livewire/Pages/Products/ProductList.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `contacts.index` | Cari Listesi | `app/Livewire/Pages/Contacts/ContactList.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `brands.index` | Markalar | `app/Livewire/Pages/Catalog/BrandList.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `units.index` | Birimler | `app/Livewire/Pages/Catalog/UnitList.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `locations.index` | Depolar | `app/Livewire/Pages/Catalog/LocationList.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `price-lists.index` | Fiyat Listeleri | `app/Livewire/Pages/Pricing/PriceListList.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `stock.status` | Stok Durumu | `app/Livewire/Pages/Stock/StockStatus.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `stock.movements` | Stok Hareketleri | `app/Livewire/Pages/Stock/StockMovements.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `stock.transfers.index` | Depo Transferleri | `app/Livewire/Pages/Stock/TransferList.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `stock.counts.index` | Stok Sayımları | `app/Livewire/Pages/Stock/StockCountList.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `stock.counts.results` | Stok Sayım Sonuçları | `app/Livewire/Pages/Stock/StockCountResults.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `stock.reservations.index` | Rezervasyonlar | `app/Livewire/Pages/Stock/ReservationList.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `stock.warehouse-slips.index` | Stok Düzeltme Fişleri | `app/Livewire/Pages/Stock/WarehouseSlipList.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `sales.quotes.index` | Teklifler | `app/Livewire/Sales/QuoteList.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `sales.orders.index` | Satış Siparişleri | `app/Livewire/Sales/SalesOrderList.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `sales.dispatches.index` | Sevkiyat / İrsaliyeler | `app/Livewire/Sales/DispatchList.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `sales.invoices.index` | Satış Faturaları | `app/Livewire/Sales/SalesInvoiceList.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `sales.proformas.index` | Proformalar | `app/Livewire/Sales/ProformaList.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `purchases.orders.index` | Satınalma Siparişleri | `app/Livewire/Purchases/PurchaseOrderList.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `purchases.receipts.index` | Mal Kabul | `app/Livewire/Purchases/GoodsReceiptList.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `purchases.invoices.index` | Alış Faturaları | `app/Livewire/Purchases/SupplierInvoiceList.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `warehouse.transport.index` | Ambar Fişleri | `app/Livewire/Pages/Warehouse/TransportSlipList.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `production.orders` | Üretim Emirleri | `app/Livewire/Production/ProductionOrderCenter.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `production.recipes` | Üretim Reçeteleri | `app/Livewire/Production/RecipeCenter.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `production.subcontracting` | Fason | `app/Livewire/Production/SubcontractingCenter.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `imports.shipments` | İthalat Dosyaları | `app/Livewire/Imports/ImportCenter.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `channels.listings` | Kanal Listingleri | `app/Livewire/Channels/ChannelListingCenter.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |
| `channels.sync` | Kanal Olayları | `app/Livewire/Channels/ChannelSyncCenter.php` | Ortak görünüm / liste sözleşmesi işlendi | Bekliyor |

## 2. Değiştirilen 35 Livewire Blade dosyası

Bu tablonun her satırı baz commit'e göre **gerçek GitHub dosya değişikliği**dir. “Sunucuda çalışıyor” anlamına gelmez.

| Dosya | Dönüşüm kapsamı | QA |
|---|---|---|
| `channels/channel-account-center.blade.php` | Görsel tablo/toolbar standardı veya özel ağaç istisnası | Bekliyor |
| `channels/channel-listing-center.blade.php` | İşlevsel filtre/sayfa düzeni ve görünüm | Bekliyor |
| `channels/channel-sync-center.blade.php` | İşlevsel filtre/sayfa düzeni ve görünüm | Bekliyor |
| `components/data-table.blade.php` | İşlevsel filtre/sayfa düzeni ve görünüm | Bekliyor |
| `finance/bank-statement-center.blade.php` | Görsel tablo/toolbar standardı veya özel ağaç istisnası | Bekliyor |
| `finance/contact-aging.blade.php` | Görsel tablo/toolbar standardı veya özel ağaç istisnası | Bekliyor |
| `finance/finance-accounts.blade.php` | Görsel tablo/toolbar standardı veya özel ağaç istisnası | Bekliyor |
| `finance/finance-operation-center.blade.php` | Görsel tablo/toolbar standardı veya özel ağaç istisnası | Bekliyor |
| `finance/securities-center.blade.php` | Görsel tablo/toolbar standardı veya özel ağaç istisnası | Bekliyor |
| `imports/import-center.blade.php` | İşlevsel filtre/sayfa düzeni ve görünüm | Bekliyor |
| `pages/catalog/category-list.blade.php` | Görsel tablo/toolbar standardı veya özel ağaç istisnası | Bekliyor |
| `pages/catalog/location-detail.blade.php` | Görsel tablo/toolbar standardı veya özel ağaç istisnası | Bekliyor |
| `pages/companies/cross-company-copy.blade.php` | Görsel tablo/toolbar standardı veya özel ağaç istisnası | Bekliyor |
| `pages/import/import-wizard.blade.php` | Görsel tablo/toolbar standardı veya özel ağaç istisnası | Bekliyor |
| `pages/pricing/price-list-detail.blade.php` | Görsel tablo/toolbar standardı veya özel ağaç istisnası | Bekliyor |
| `pages/products/variant-group-detail.blade.php` | Görsel tablo/toolbar standardı veya özel ağaç istisnası | Bekliyor |
| `pages/settings/integrity-report.blade.php` | Görsel tablo/toolbar standardı veya özel ağaç istisnası | Bekliyor |
| `pages/settings/master-settings-index.blade.php` | Görsel tablo/toolbar standardı veya özel ağaç istisnası | Bekliyor |
| `pages/settings/operations-center.blade.php` | Görsel tablo/toolbar standardı veya özel ağaç istisnası | Bekliyor |
| `pages/settings/period-carry.blade.php` | Görsel tablo/toolbar standardı veya özel ağaç istisnası | Bekliyor |
| `pages/settings/periods.blade.php` | Görsel tablo/toolbar standardı veya özel ağaç istisnası | Bekliyor |
| `pages/stock/quarantine-control.blade.php` | Görsel tablo/toolbar standardı veya özel ağaç istisnası | Bekliyor |
| `pages/warehouse/transport-slip-list.blade.php` | İşlevsel filtre/sayfa düzeni ve görünüm | Bekliyor |
| `production/production-order-center.blade.php` | İşlevsel filtre/sayfa düzeni ve görünüm | Bekliyor |
| `production/recipe-center.blade.php` | İşlevsel filtre/sayfa düzeni ve görünüm | Bekliyor |
| `production/subcontracting-center.blade.php` | İşlevsel filtre/sayfa düzeni ve görünüm | Bekliyor |
| `purchases/supplier-performance.blade.php` | Görsel tablo/toolbar standardı veya özel ağaç istisnası | Bekliyor |
| `reporting/consolidated-report-center.blade.php` | Görsel tablo/toolbar standardı veya özel ağaç istisnası | Bekliyor |
| `reporting/dashboard.blade.php` | Görsel tablo/toolbar standardı veya özel ağaç istisnası | Bekliyor |
| `reporting/document-template-designer.blade.php` | Görsel tablo/toolbar standardı veya özel ağaç istisnası | Bekliyor |
| `reporting/export-center.blade.php` | Görsel tablo/toolbar standardı veya özel ağaç istisnası | Bekliyor |
| `reporting/print-history.blade.php` | Görsel tablo/toolbar standardı veya özel ağaç istisnası | Bekliyor |
| `reporting/report-center.blade.php` | Görsel tablo/toolbar standardı veya özel ağaç istisnası | Bekliyor |
| `returns/return-center.blade.php` | Görsel tablo/toolbar standardı veya özel ağaç istisnası | Bekliyor |
| `sales/document-list.blade.php` | İşlevsel filtre/sayfa düzeni ve görünüm | Bekliyor |

## 3. Şablon dışı bırakılan işlem/detay ekranları

Bu alanlarda tablo bulunmasına rağmen belgenin düzenleme/satır işlem biçimi iş akışına bağlıdır. Liste arayüzüne zorla dönüştürülmedi. Genel `.data-table` stili korunur.

- `resources/views/livewire/sales/proforma-detail.blade.php`
- `resources/views/livewire/pages/catalog/unit-form.blade.php`
- `resources/views/livewire/pages/contacts/contact-form.blade.php`
- `resources/views/livewire/sales/vehicle-hot-sale.blade.php`
- `resources/views/livewire/pages/stock/warehouse-slip-detail.blade.php`
- `resources/views/livewire/pages/stock/transfer-detail.blade.php`
- `resources/views/livewire/pages/products/product-detail.blade.php`
- `resources/views/livewire/purchases/document-editor.blade.php`
- `resources/views/livewire/sales/document-editor.blade.php`
- `resources/views/livewire/pages/products/product-form.blade.php`
- `resources/views/livewire/pages/stock/stock-count-detail.blade.php`
- `resources/views/livewire/pages/warehouse/transport-slip-editor.blade.php`

## 4. Tamamlanmış kod / açık kabul işleri

- **Kod:** 28 ana rota envanterlendi; 35 Blade'de kaynak değişikliği; filtre/satır/kolon/footer bileşenleri ortaklaştırıldı.
- **Kapsam sınırlaması:** 35 Blade'deki her alt tabloya ayrı veri araması, server-side pagination veya kolon seçici getirilmedi. İşlem bileşenlerinin kaynak sözleşmesi korundu. Raporlarda görünüm düzeltmesi ile işlevsel dönüşüm ayrı gösterilmiştir.
- **Açık kalite kapısı:** PHP/Blade/Vite kod doğrulaması, yeni UI sözleşme testleri, PostgreSQL izolasyon testleri, 1920/1440/1024/768/390px browser QA, CSV/XLSX kontrolü.
- **İzin:** Test çalıştırma ve `18082` Fresh web dağıtımı için ayrıca kullanıcı onayı alınacak.
- **Veri güvenliği:** PROD/Fresh PostgreSQL, Valkey, demo kayıtları, Docker volume ve kurulum sihirbazına müdahale edilmedi.

## 5. Kodlama kanıtları

- Ortak `resources/views/components/v65/list-footer.blade.php`
- Ortak `resources/views/components/v65/list-filters.blade.php`
- Ortak `app/Livewire/Concerns/WithV65DocumentList.php`
- `tests/Unit/UI/V65ListStandardizationTest.php` (**yazıldı, çalıştırılmadı**)
- `docs/quality/v65-list-standardization-2026-10-13.md`

**Sonuç:** Kaynak değişiklikleri yapılmıştır; testlerden ve görsel kabulden geçmeden sürüm yayına hazır ilan edilemez.
