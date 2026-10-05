# Dönem devri kontrol ve bütünlük

## Preview zorunludur

Carry başlamadan önce source period için read-only preview üretilir.

Kontroller:
- period açık/uygun durumda mı,
- target year mevcut mu,
- target DB daha önce business data almış mı,
- açık teklif/taslak/yoldaki transfer var mı,
- aktarılacak açık sales_order/purchase_order ve kalan miktar/rezervasyon özeti,
- K-259 kapsamındaki açık import_file ve konteyner/paket/maliyet kalemi snapshot özeti,
- açık production/subcontract order var mı,
- açık quarantine kayıtları,
- stock/contact/cash/bank/security kapanış toplamları,
- product_costs snapshot'ları,
- recipe/listing mapping sayıları,
- period access kopyalama adayları.

## Bloklayan durumlar

- target period business data içeriyorsa otomatik carry yok,
- migration başarısızsa copy yok,
- integrity:carry farkı varsa source closed yapılmaz,
- açık production/subcontract order varsa kullanıcı tamamlamadan/iptal etmeden carry tamamlanmaz,
- yoldaki transfer varsa carry tamamlanmaz.

Açık quarantine blok değildir; yeni döneme taşınır. Açık sales_order/purchase_order da blok değildir; K-256 carry akışıyla yeni period'a kalan miktar snapshot'ı olarak aktarılır. K-259 gereği henüz stoğa alınmamış açık import_file da blok değildir; target period'da yeni numara ve source provenance ile operational snapshot olarak devam eder.

## Taşınanlar

- aktif kartlar + ilişkili gerekli pasif kartlar,
- ID/kod sürekliliği,
- stock_balance id,
- opening stock + moving average,
- product_costs,
- cari/kasa/banka opening,
- vadesi gelmemiş securities,
- açık quarantine,
- production recipe/revision,
- subcontractor location stock,
- channel account period settings + channel listing/location mapping,
- K-256 gereği kalan açık miktarlarıyla sales_order/purchase_order snapshot'ları ve sales-order aktif rezervasyonları; target sipariş target yılın kendi numara serisinden yeni numara alır, source numara `period_document_carries.source_document_number` provenance'ında korunur,
- taşınan açık sales_order kanal kaynaklıysa gerekli `channel_order_snapshot` aktif provenance kaydı,
- K-259 gereği açık `draft|in_transit|customs` import file: target yılın yeni import numarası + source period/file/number provenance; container/package/cost-item operasyon snapshotları. Kur kilidi, TRY/allocation/landed-cost snapshotları taşınmaz ve target'ta yeniden hesaplanır.

## Taşınmayanlar

- geçmiş documents,
- geçmiş stock/contact/cash/bank movements,
- açık satış/satınalma teklif ve taslakları; K-259 açık import file draft istisnadır,
- source period'daki geçmiş/tamamlanmış siparişler; yalnız K-256 kapsamındaki açık kalan sales_order/purchase_order target snapshot'a dönüşür,
- yoldaki transfer,
- açık production/subcontract order,
- geçmiş/tamamlanmış channel order snapshot ve sync history; yalnız K-256 ile taşınan açık kanal sales_order'ın aktif provenance snapshot'ı istisnadır,
- report/export/print job history.

## Finalizasyon

integrity:carry geçmeden:
- source closed olmaz,
- target active olarak ilan edilmez.

Explicit ID ile kopyalanan bir tabloda, aynı tabloya normal/auto-ID insert yapılmadan önce sequence `MAX(id)+1` seviyesine alınır; carry sonunda ilgili sequence'ler yeniden doğrulanır.

Başarı sonrası access/permission override kopyalama ayrı kullanıcı adımıdır.
