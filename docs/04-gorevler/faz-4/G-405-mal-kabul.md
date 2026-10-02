# G-405 — Mal kabul / alış irsaliyesi

## Amaç

K-087'ye göre stok ve cari etkisiz operasyonel goods_receipt belgesini ve kısmi teslim kaydını uygulamak.

## Önkoşul

G-401, G-404.

## Dokunulacak dosyalar

- goods receipt create/detail bileşenleri
- `PostGoodsReceipt` Action
- order→goods_receipt conversion
- goods receipt feature/concurrency testleri

## Şema / Kod

goods_receipt mevcut documents/document_lines kullanır.

- contact_id zorunlu.
- location_id teslim hedefini taşır.
- source_line_id purchase_order line olabilir.
- post sonrası belge fulfillment hesabına dahil olur.

## Kurallar

- **Stock movement yazma.**
- **Contact transaction yazma.**
- **UpdateMovingAverage çağırma.**
- Order remaining aşılmaz.
- Posted belge immutable.
- Doğrudan goods_receipt oluşturulabilir.
- Ekran stok artışının alış faturasında olacağını açıkça gösterir.

## Kabul ölçütü

- Kısmi receipt doğru order remaining azaltıyor.
- Aynı order line birden fazla receipt'e bölünebiliyor.
- Remaining üstü receipt reddediliyor.
- Post sonrası stock_movements sayısı değişmiyor.
- Post sonrası contact_transactions sayısı değişmiyor.
- product_costs değişmiyor.
- Concurrent receipt toplamı kalan miktarı aşamıyor.

## İstem

> goods_receipt'i yalnız operasyonel fulfillment belgesi olarak uygula. K-087'ye aykırı stok/cari/maliyet yan etkisi ekleme.
