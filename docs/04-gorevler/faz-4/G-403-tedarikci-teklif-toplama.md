# G-403 — Tedarikçi teklif toplama ve karşılaştırma

## Amaç

Bir satınalma talebine birden fazla supplier_quote bağlamak; K-090/K-091'e göre belge ve satır bazlı kullanıcı seçimini uygulamak.

## Önkoşul

G-401, G-402.

## Dokunulacak dosyalar

- supplier quote create/detail bileşenleri
- teklif karşılaştırma bileşeni
- `SelectSupplierQuoteLine` / toplu seçim Action'ı
- selection→purchase_order dönüşüm Action'ı
- purchasing quote feature testleri

## Şema / Kod

supplier_quote:

- contact_id zorunlu supplier,
- currency/exchange_rate ortak documents alanları,
- source_line_id request line'a bağlanabilir,
- stok/cari posting yok.

Seçim gerçek kaynağı `purchase_quote_selections`.

Belge bazlı seçim, uygun request satırları için toplu selection satırları üretir.

## Kurallar

- Sistem otomatik en ucuz/kazanan seçmez.
- Ayrı approval state ve tutar eşiği yok.
- Seçim `purchasing.quote.select` iznine bağlı.
- Seçim/değişiklik audit edilir.
- Siparişe dönüşmüş seçim yerinde değiştirilmez.
- Satır bazlı seçimlerde purchase_order'lar supplier contact'a göre ayrılır.

## Kabul ölçütü

- Aynı request'e birden fazla supplier_quote eklenebiliyor.
- Belge bazlı seçim doğru tüm selection satırlarını üretiyor.
- Satır bazlı seçim farklı supplier'ları destekliyor.
- Yetkisiz kullanıcı seçim yapamıyor.
- Sistem kendiliğinden winner seçmiyor.
- Aynı selection ikinci kez siparişe dönüşmüyor.
- Audit eski/yeni seçim ve actor içeriyor.

## İstem

> K-090/K-091 teklif toplama modelini purchase_quote_selections üzerinden uygula. Otomatik puanlama veya approval workflow ekleme.
