# Mimari

## Veritabanı katmanları

`MarsProject_Master` sistem üstü bağlamdır. `ABCHolding_2026`, `ABCHolding_2027`, `XYZltd_2026` gibi DB'ler şirket+yıl period veritabanlarıdır.

### Master

`companies`, `periods`, `users`, rol/izin tabloları, `company_user`, şirket+dönem erişim kayıtları, `exchange_rates`, `app_settings`, `company_copy_permissions`, `print_profiles`, `document_templates`, `report_filter_presets`, `report_export_jobs`, `print_jobs`, `sales_channel_accounts`, `channel_external_event_registry`, deployment/backup/restore/health operasyon tabloları ve master `activity_log`.

### Period

Cari/ürün kartları ve yan tabloları, fiyat listeleri, varyant/set/konfigürasyon, lokasyonlar, kart ekleri, documents/document_lines ve period_document_carries, stok hareket/bakiyeleri, maliyet, cari hareket, kasa/banka, çek/senet, iade/quarantine, ithalat, üretim/fason, e-ticaret listing/order/sync dönem kayıtları, number series, posting period, rezervasyon, sayım ve period audit.

## İzolasyon ve bağlantı

Kullanıcının şirket+dönem erişimi Master'dan doğrulanmadan `PeriodContext` kurulmaz. Period DB seçildikten sonra tüm `PeriodModel` sorguları fiziksel olarak yalnız o DB'ye gider. `company_id` filtresi kullanılmaz.

Aynı period içindeki `documents.contact_id -> contacts.id` ve `document_lines.product_id -> products.id` gibi ilişkiler gerçek FK'dir. Master `users` farklı DB'de olduğu için `created_by/posted_by` gibi alanlarda gerçek FK yoktur; scalar user_id + user_name snapshot kullanılır.

## Yazma ve bütünlük

Stok yalnız `RecordStockMovement` üzerinden yazılır. Belge kesinleştirme transaction, idempotency, lockForUpdate ve aynı transaction içinde post-write verify kullanır. Cari bakiye `contact_transactions` toplamıdır. Türetilmiş/kopyalanmış her değer için `integrity:` kontrolü bulunur.

## Arşiv

Period DB verisi Master olmadan okunabilir. Uygulamada login, erişim kontrolü ve Master'daki yazdırma profilleri için Master gerekir.


## Dönem devri özeti

Kartlar ve gerekli stock_balance kimlikleri aynı ID/kodla taşınır. Açık quarantine yeni period'a taşınır. K-256 gereği açık sales_order/purchase_order yalnız kalan miktarlarıyla yeni confirmed snapshot'a dönüşür ve sales-order aktif rezervasyonları location bazında yeniden kurulur. Teklif/taslak, geçmiş hareket/belge, yoldaki transfer ve açık production/subcontract order taşınmaz.
