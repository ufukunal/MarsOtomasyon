# Dönem devri kontrol ve bütünlük

## Preview zorunludur

Carry başlamadan önce source period için read-only preview üretilir.

Kontroller:
- period açık/uygun durumda mı,
- target year mevcut mu,
- target DB daha önce business data almış mı,
- açık teklif/taslak/yoldaki transfer var mı,
- aktarılacak açık sales_order/purchase_order ve kalan miktar/rezervasyon özeti,
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

Açık quarantine blok değildir; yeni döneme taşınır. Açık sales_order/purchase_order da blok değildir; K-256 carry akışıyla yeni period'a kalan miktar snapshot'ı olarak aktarılır.

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
- K-256 gereği kalan açık miktarlarıyla sales_order/purchase_order snapshot'ları ve sales-order aktif rezervasyonları,
- taşınan açık sales_order kanal kaynaklıysa gerekli `channel_order_snapshot` aktif provenance kaydı.

## Taşınmayanlar

- geçmiş documents,
- geçmiş stock/contact/cash/bank movements,
- açık teklif ve taslak,
- source period'daki geçmiş/tamamlanmış siparişler; yalnız K-256 kapsamındaki açık kalan sales_order/purchase_order target snapshot'a dönüşür,
- yoldaki transfer,
- açık production/subcontract order,
- geçmiş/tamamlanmış channel order snapshot ve sync history; yalnız K-256 ile taşınan açık kanal sales_order'ın aktif provenance snapshot'ı istisnadır,
- report/export/print job history.

## Finalizasyon

integrity:carry geçmeden:
- source closed olmaz,
- target active olarak ilan edilmez.

Başarı sonrası access/permission override kopyalama ayrı kullanıcı adımıdır.
