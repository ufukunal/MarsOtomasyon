# Ekran — İrsaliye / Sevkiyat Detayı

**v65 referansı:** `dispatch_list`, `dispatch_new`, `dispatch_detail`.

## Liste kolonları

- Sevk No
- Sipariş
- Cari
- Depo
- Tarih
- Toplama
- Paket
- Kargo
- Takip
- Teslim
- Durum

## Sekmeler

v65:

- Hareketler
- Bilgiler
- Toplama
- Paketler
- Kargo
- Pre-Shipment QC
- Dosyalar
- Timeline

**Kapsam override:** kalite modülü kapsam dışı olduğu için `Pre-Shipment QC` Faz 3'te uygulanmaz.

## Eylemler

Yeni:

- Kaydet
- Toplamaya Başla
- Post

Detay:

- Topla
- Paketle
- Post
- Kargoya Teslim
- Reverse

## Posting etkisi

İrsaliye **fiziksel sevk** belgesidir:

- ilgili rezervler çözülür,
- `RecordStockMovement(out)` yazılır,
- cari hareket yazılmaz.

Siparişten kısmi sevk mümkündür. Child satır `source_line_id` ile sipariş satırına bağlanır.

## Lokasyon

K-073 gereği gerçek çıkış lokasyonu satırdadır. Bir sipariş satırı farklı depolardan karşılanıyorsa sevk satırları lokasyon bazında ayrılır.

## Faturalama

İrsaliye bir veya birden fazla satış faturasına bölünebilir. Faturaya dönüştürülen satır için stok **ikinci kez** düşmez.
