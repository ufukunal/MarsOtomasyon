# Veri bütünlüğü

Sistem stok/cari/kasanın tek kaydıdır. Bütünlük dört katmanda korunur: DB CHECK, transaction içi post-write verify, zamanlanmış integrity komutları, gerekçeli manuel düzeltme.

## Zorunlu kontroller

- `integrity:stock`: stock_movements toplamı ↔ stock_balances
- `integrity:contacts`: contact_transactions toplamı ↔ raporlanan bakiye
- `integrity:documents`: **line_calculated** belgelerde satırlar ↔ belge toplamları / rounding_difference; **header_amount** (`collection`, `contact_debit_credit`) belgelerde satır beklemeden amount/header invariant'ı + ilgili cari/finans hareketi tutarı
- `integrity:numbers`
- `integrity:costs`
- `integrity:reservations`
- `integrity:quarantine`
- `integrity:units`: base_quantity = quantity × frozen conversion_factor
- `integrity:partials`: ordered/shipped/invoiced/cancelled sınırları
- `integrity:files`
- `integrity:carry`
- ileriki fazlarda cash, securities, landed_cost, production, channels

Kart-belge referansları artık aynı period DB'de gerçek FK ile korunur. Eski “master kart referansını integrity:references ile ara” yaklaşımı geçersizdir.

Belge snapshot ad/kodunun güncel kartla aynı olması zorunlu değildir; snapshot tarihsel çıktıdır ve kart sonradan değişebilir.

`integrity:all` gecelik çalışır. Fark varsa bildirir, **otomatik düzeltmez**. Her türetilmiş/kopyalanmış değer eklendiği görevde kendi integrity kontrolünü de alır.
