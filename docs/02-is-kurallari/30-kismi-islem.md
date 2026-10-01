# Kısmi işlem

Kısmi sevk ve kısmi fatura kaynak satırların child satırları üzerinden izlenir. Aynı miktar için ikinci ayrı "shipped_quantity/invoiced_quantity" gerçek kaynağı oluşturulmaz.

## Sipariş satırı

```
ordered   = source line.quantity
cancelled = source line.cancelled_quantity
shipped   = order line'i source_line_id olarak gösteren posted dispatch line toplamı
direct_invoiced = order line'i source_line_id olarak gösteren doğrudan invoice line toplamı

kalan sevk = ordered - cancelled - shipped - direct_invoiced
```

Aynı miktar hem doğrudan fatura hem irsaliye ile tekrar karşılanamaz.

## Kalanı iptal

Örnek:

- sipariş: 100
- sevk: 70
- kalan iptal: 30

Kaynak quantity 100 olarak kalır. `cancelled_quantity = 30`.

Bu 30 artık:

- rezerve edilemez,
- sevk edilemez,
- faturalanamaz.

Ayrı iptal belgesi veya quantity'yi 70'e düşürme yapılmaz.

Kısmen karşılanıp kalan tamamen iptal edilince sipariş status = `closed`.

## Kısmi sevk

Sipariş satırından sevk oluştururken kullanıcı sevk miktarını seçer. Sistem:

1. kalan miktarı hesaplar,
2. seçilen miktarın kalanı aşmadığını doğrular,
3. rezerv dağılımı varsa lokasyonlara göre child dispatch satırları üretir,
4. dispatch posting ile rezervi çözer ve stok düşürür.

## Kısmi fatura

Bir irsaliye:

- bugün 60,
- sonra 40

şeklinde birden fazla faturaya bölünebilir.

Bir fatura aynı cariye ait birden fazla irsaliyeyi birleştirebilir; K-076 gereği para birimi ve satış koşulları uyumlu olmalıdır.

Faturalanabilir irsaliye miktarı:

```
dispatch quantity - posted invoice child line toplamı
```

## Belge ilişkileri

Belge başlık zinciri `document_relations`, miktar zinciri `source_line_id` ile izlenir.

Örnek:

```
sales_order
  line 10: 100
    ├─ dispatch A line: 70
    │    ├─ invoice X line: 40
    │    └─ invoice Y line: 30
    └─ cancelled_quantity: 30
```

## Rezervasyon

- Rezerv yalnız mevcut kullanılabilir stok kadar oluşur.
- Bir sipariş satırı birden fazla lokasyona bölünebilir.
- Kısmi sevk yalnız sevk edilen rezervleri çözer.
- Kalan rezerv açık sipariş için korunur.
- cancelled_quantity ile ilgili rezerv varsa önce çözülür; iptal miktarı için yeni rezerv oluşturulamaz.

## Yarış koşulu

Sevk/fatura oluşturma sırasında kaynak satır ve ilgili rezervler transaction içinde tekrar okunur/kilitlenir. İki kullanıcı aynı kalan miktarı aynı anda kullanamaz.

## integrity:partials

- child toplamları kaynak miktarı aşmamalı,
- cancelled + fulfilled toplamı quantity'yi aşmamalı,
- aynı dispatch miktarı toplam invoice miktarından küçük olmamalı,
- closed siparişte kullanılabilir kalan 0 olmalı.

Fark raporlanır; otomatik düzeltme yapılmaz.
