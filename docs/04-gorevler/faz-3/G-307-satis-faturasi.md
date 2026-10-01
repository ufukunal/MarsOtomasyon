# G-307 — Satış faturası ve kısmi fatura

## Amaç

İrsaliye, sipariş veya doğrudan girişten satış faturası üretmek; cari borçlandırmayı ve yalnız gerektiğinde stok çıkışını doğru yapmak.

## Önkoşul

G-303, G-305, G-306.

## Dokunulacak dosyalar

- `app/Actions/Sales/CreateInvoiceFromDispatches.php`
- `app/Actions/Sales/CreateInvoiceFromOrder.php`
- `app/Actions/Sales/PostSalesInvoice.php`
- `app/Livewire/Sales/SalesInvoiceList.php`
- `app/Livewire/Sales/SalesInvoiceEditor.php`
- `tests/Feature/Sales/SalesInvoiceTest.php`


## Şema / Kod

Yeni tablo yok. Invoice aynı `documents/document_lines` şemasını kullanır. Cari etki `contact_transactions`, stok etki gerekiyorsa `stock_movements`; kaynak ilişkileri `document_relations` + `source_line_id` ile tutulur.

## Ekran

Faz 3 tabs:

- Hareketler
- Bilgiler
- Ödemeler
- Cari Bakiye
- Notlar
- Dosyalar
- PDF
- Timeline

Eylemler:

- Kaydet
- Post Et
- Tahsilat
- Reverse
- PDF

v65 E-Belge Faz 3'e alınmaz. Adjustment yerine G-311 ters kayıt kullanılır.

## Vade

Yeni faturada:

1. `contacts.term_days` varsa onu kullan,
2. yoksa Master `companies.default_term_days` (default 30),
3. kullanıcı taslakta due_date değiştirebilir.

## İrsaliyeden fatura

Bir veya birden fazla irsaliye seçilebilir. Her kaynak irsaliye için `dispatch_to_invoice` relation yazılır.

Birleştirme için:

- aynı contact,
- aynı para birimi,
- uyumlu satış koşulları

zorunludur.

Her invoice line:

- `source_line_id = dispatch_line.id`
- miktar = henüz faturalanmamış dispatch miktarı veya seçilen daha küçük miktar.

Posting:

- contact transaction **debit**,
- **stok hareketi yok**.

## Bir irsaliyeyi bölme

100 dispatch quantity:

- invoice A 60
- invoice B 40

olabilir.

`dispatch quantity - posted invoice child total` altına düşülmez, üstüne çıkılmaz.

## Siparişten doğrudan fatura

Siparişten irsaliyesiz fatura mümkündür. Başlık seviyesinde `order_to_invoice` relation yazılır.

- source_line_id = order_line.id
- order kalan miktarı aşılmaz
- ilgili rezerv varsa `ConsumeReservation` ile state/reserved çözülür; bu Action stock movement yazmaz
- invoice posting **tek stock out + cari debit** üretir.

Aynı order miktarı daha sonra dispatch ile tekrar kullanılamaz.

## Doğrudan fatura

Source line yoksa:

- product/unit/location zorunlu,
- posting stok out,
- cari debit.

## Hesap

G-302 zorunlu.

Taslakta:

- Tümüne KDV uygula
- KDV temizle

eylemleri bulunur.

%20+ fiyat sapması uyarı+audit; blok yok. Maliyet altı satış uyarısı cost.view kuralına uyar.

## Cari hareket

Posted faturada:

```
transaction_type = sales_invoice
direction = debit
amount = grand_total
due_date = document.due_date
document_id = invoice.id
```

Aynı invoice için ikinci cari hareket unique constraint/idempotency ile oluşmaz.


## Kurallar

- İrsaliyeden faturada stok ikinci kez düşmez.
- Direct faturada stok + cari aynı transaction içinde oluşur.
- Kaynak belge varsa başlık relation ve satır `source_line_id` zinciri birlikte yazılır.
- Fatura bakiyesi invoice-settlement tablosuna bağlanmaz.
- E-Belge Faz 3 kapsamı dışıdır.

## Kabul ölçütü

- Dispatch kaynaklı invoice stok düşürmüyor.
- Direct invoice stok + cari etkisi oluşturuyor.
- Order direct invoice rezervi doğru tüketiyor.
- Bir dispatch 60/40 iki faturaya bölünebiliyor.
- Faturalanan toplam dispatch miktarını aşamıyor.
- Birden fazla uyumlu dispatch tek faturada birleşiyor.
- Farklı cariler tek faturada birleşemiyor.
- due_date contact/company default ile doğru.
- E-Belge route/tab oluşturulmuyor.
- Posted invoice immutable.

## İstem

> Satış faturası list/edit ekranlarını ve üç oluşturma yolunu uygula: irsaliyeden, siparişten doğrudan, bağımsız doğrudan. İrsaliyeden faturada stok ikinci kez düşmesin; direct faturada stok + cari aynı transaction'da oluşsun. Kısmi fatura source_line_id toplamıyla korunsun.
