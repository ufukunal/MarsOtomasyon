# G-308 — Proforma

## Amaç

Teklif veya siparişten stok/cari etkisi olmayan numaralı proforma üretmek ve gerektiğinde satış faturasına kaynak yapmak.

## Önkoşul

G-301, G-302, G-304, G-305.

## Dokunulacak dosyalar

- `app/Actions/Sales/IssueProforma.php`
- `app/Actions/Sales/ConvertProformaToInvoice.php`
- `app/Livewire/Sales/ProformaList.php`
- `app/Livewire/Sales/ProformaDetail.php`
- `tests/Feature/Sales/ProformaTest.php`


## Şema / Kod

Yeni tablo yok. Proforma `documents/document_lines` kullanır; kaynak bağlantısı `document_relations` ve `source_line_id` ile tutulur. Stok/cari tablo yazımı yoktur.

## Kaynak kararı

Güncel `number_series` belgesi `proforma` serisini içerir ve v65 Proforma No gösterir. Bu güncel kaynaklar eski "proforma numarasız" devir notundan üstündür.

Bu nedenle proforma **numaralıdır**.

## Ekran

v65:

Liste:
- Proforma No
- Cari
- Teklif/Sipariş
- Tarih
- Geçerlilik
- Tutar
- Para Birimi
- PDF
- Durum

Detay tabs:
- Hareketler
- Bilgiler
- Notlar
- PDF
- Timeline

Eylemler:
- PDF
- Satış Faturası Oluştur

v65 ayrı `proforma_new` tanımlamaz; proforma teklif/sipariş bağlamından üretilir.

## IssueProforma

Tek transaction:

1. kaynak quote veya sales_order doğrula,
2. `EnsurePeriodOpen(document_date)`,
3. `GenerateDocumentNumber('proforma')`,
4. header/lines snapshot kopyala,
5. source_line_id bağlantıları,
6. document relation kaynağa göre yaz,
7. status = `posted`, posted_at ve actor snapshot,
8. audit.

**Stock, reservation veya contact_transaction yazılmaz.**

## Faturaya dönüştürme

Proforma stok hareketi olmadığı için proforma kaynaklı invoice **doğrudan satış faturası** etkisi taşır:

- invoice line source_line_id = proforma line,
- `proforma_to_invoice` relation,
- invoice posting stok out + cari debit,
- ürün/lokasyon miktar doğrulamaları G-307 ile aynı.

Proforma conversion kaynak quote/order'ın fulfillment miktarını iki kez tüketmemeli. Eğer proforma zaten order'dan üretilmişse invoice miktar kontrolü order zincirine kadar takip edilerek çift satış engellenir.


## Kurallar

- Proforma numaralı ve immutable final çıktıdır.
- Stok, rezerv veya cari etkisi yoktur.
- Invoice dönüşümünde çift fulfillment engellenir.

## Kabul ölçütü

- Proforma period proforma serisinden numara alıyor.
- Quote/order snapshot'ı doğru kopyalanıyor.
- Proforma hiçbir stok/cari hareket üretmiyor.
- PDF için finalized proforma immutable.
- Proforma invoice'a dönüşünce invoice normal stok+cari etkisini üretiyor.
- Aynı kaynak miktar proforma üzerinden ve order üzerinden iki kez faturalanamıyor.
- Gerçek PostgreSQL testleri geçiyor.

## İstem

> IssueProforma ve ConvertProformaToInvoice akışını uygula. Proforma numaralı fakat stok/cari etkisiz olsun. v65'teki liste/detay ekranını kullan; yeni proforma formu uydurma. Invoice dönüşümünde kaynak quantity'nin iki kez tüketilmesini engelle.
