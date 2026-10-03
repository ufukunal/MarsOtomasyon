# Dönem devri kontrol ve bütünlük

## Preview zorunludur

Carry başlamadan önce source period için read-only preview üretilir.

Kontroller:
- period açık/uygun durumda mı,
- target year mevcut mu,
- target DB daha önce business data almış mı,
- açık teklif/sipariş/taslak/yoldaki transfer var mı,
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

Açık quarantine blok değildir; yeni döneme taşınır.

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
- channel account period settings + channel listing/location mapping.

## Taşınmayanlar

- geçmiş documents,
- geçmiş stock/contact/cash/bank movements,
- açık teklif/sipariş/taslak,
- yoldaki transfer,
- açık production/subcontract order,
- channel order/sync history,
- report/export/print job history.

## Finalizasyon

integrity:carry geçmeden:
- source closed olmaz,
- target active olarak ilan edilmez.

Başarı sonrası access/permission override kopyalama ayrı kullanıcı adımıdır.
