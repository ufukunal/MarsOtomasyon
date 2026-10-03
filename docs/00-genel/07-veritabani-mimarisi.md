# Veritabanı mimarisi — ince Master + şirket/dönem

**Kanonik mimari belgesidir.**

## Yapı

```
MarsProject_Master
ABCHolding_2026
ABCHolding_2027
XYZltd_2026
```

## Master'da duranlar

- companies
- periods
- users
- roles / permissions ve ilişkileri
- company_user
- şirket+dönem erişim kayıtları
- exchange_rates
- app_settings
- company_copy_permissions
- print_profiles
- document_templates
- report_filter_presets
- report_export_jobs
- print_jobs
- deployment_runs
- backup_runs
- restore_runs
- operational_heartbeats
- health_check_runs
- sales_channel_accounts
- channel_external_event_registry
- master activity_log

Master'da cari/ürün/lokasyon/fiyat kartı yoktur. Master modelleri şirket global scope'u kullanmaz.

## Period DB'de duranlar

Kartlar: contacts ve yan tabloları, products, units, categories, brands, variants, sets, configurator, price_lists, locations, kart attachments/görseller.

Operasyon: documents, document_lines, document_relations, contact_transactions, stock_movements, stock_balances, product_costs, cash/bank, securities, import_files/import_expenses/import allocations/inventory_cost_adjustments, production_recipes/production_orders/production_completions/production_consumptions/production_outputs, channel_product_listings/channel_listing_locations/channel_order_snapshots/channel_sync_events/channel_sync_errors, document_print_snapshots, number_series, posting_periods, reservations, stock counts, quarantine, integrity/idempotency kayıtları ve period activity_log.

**Period tablolarında company_id yoktur.** `PeriodModel` connection=`period` kullanır.

## Foreign key sınırı

Aynı period DB içindeki ilişkiler gerçek FK'dir. Master'daki users/companies gibi başka DB kayıtlarına PostgreSQL cross-database FK kurulmaz. Bu referanslar scalar kimlik + gerektiğinde snapshot ile tutulur.

## Bağlantılar

Kalıcı bağlantılar `master` ve `period`dur. Şirketler arası kopyalamada işlem süresince `period_source` açılır. Kuyruk işi company_id + period_id/yıl bağlamını taşır ve `handle()` başında `PeriodContext` kurar.

## Şirketler arası kopyalama

Master `company_copy_permissions` kaynak→hedef iznini tutar. Kaynak aynı yılın period DB'sinden okunur, hedef period DB'ye yeni kayıt yazılır. Hedefte yeni ID üretilir; `source_company_id + source_record_id` provenance'dır ve FK değildir. Kod çakışırsa kullanıcıdan mevcut kart / yeni kod / iptal seçimi alınır.

## Dönem devri

1. Hedef DB oluşturulur ve `migrate:periods` çalışır.
2. Aktif kartlar + bakiye/hareket ilişkili gerekli pasif kartlar kopyalanır.
3. Aynı şirket devrinde taşınan bütün kartların ID/kodları ve taşınan stock_balance ID'leri korunur.
4. Sequence'ler `MAX(id)+1` seviyesine alınır.
5. Açılış stoku kapanış miktarı ve kapanış hareketli ortalama maliyetiyle yazılır; geçmiş stock_movements taşınmaz.
6. product_costs, cari açılış, kasa/banka, vadesi gelmemiş çek/senet ve **açık karantina miktar/snapshot kayıtları** taşınır.
7. Belgeler, açık teklif/sipariş, taslak, yoldaki transfer ve açık production/subcontract order taşınmaz. Aktif production recipe/revision kartları ve channel-product listing/location mapping'leri taşınır. Kanal order/sync history taşınmaz. Karantina bekleyen kayıtlar açık miktarıyla yeni döneme taşınır; subcontractor location fiziksel stokları normal location açılışı gibi taşınır.
8. `integrity:carry` fark bulursa devir tamamlanmaz.
9. Kaynak dönem kapatılır.
10. Sonunda önceki dönemin kullanıcı/dönem erişim ve dönemsel kullanıcı yetkilerini yeni döneme kopyalamak isteyip istemediği sorulur; kullanıcı seçilebilir.

## Çok dönemli rapor

Period DB'ler ayrı sorgulanır; ilk sürümde sonuç PHP'de birleştirilir. FDW/dblink zorunlu değildir.

## Migration / restore

Master migration'dan sonra tüm kayıtlı period DB'ler `migrate:periods` ile güncellenir. Restore edilmiş arşiv DB önce migrate edilir, sonra closed/salt-okunur açılır.
