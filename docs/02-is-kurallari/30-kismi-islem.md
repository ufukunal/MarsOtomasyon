# Kısmi işlem

Kısmi sevk ve kısmi fatura kaynak satırların child satırları üzerinden izlenir. Aynı miktar için ikinci ayrı "shipped_quantity/invoiced_quantity" gerçek kaynağı oluşturulmaz.

## Sipariş satırı

```
ordered   = source line.quantity
cancelled = source line.cancelled_quantity
shipped   = order line'i source_line_id olarak gösteren ETKİN posted dispatch line toplamı
direct_invoiced = ancestry zinciri sipariş satırına ulaşan, fakat ancestry içinde posted dispatch bulunmayan ETKİN invoice line toplamı

ETKİN = kendisini hedef alan bir `reversal_of` ilişkisi bulunmayan belge.

kalan sevk = ordered - cancelled - shipped - direct_invoiced
```

Aynı miktar hem doğrudan fatura hem irsaliye ile tekrar karşılanamaz.

### Satır ancestry çözümü

`source_line_id` yalnız tek parent saklar; ancak fulfillment sorgusu yalnız bir seviye child bakmaz. `ResolveSourceLineage` benzeri tek helper invoice/proforma satırından parent'ları geriye doğru izler:

1. visited line id seti ile cycle engellenir,
2. parent document type okunur,
3. `sales_order` satırına ulaşılırsa order origin belirlenir,
4. zincirde etkin posted `dispatch` satırı varsa invoice **dispatch üzerinden faturalanmış** kabul edilir ve order için ayrıca `direct_invoiced` sayılmaz,
5. order'a ulaşılıp zincirde dispatch yoksa invoice order'ın `direct_invoiced` miktarına dahil edilir,
6. quote kökünde biten zincir order fulfillment'ı etkilemez.

Böylece `order → proforma → invoice` doğrudan invoice fulfillment olarak, `order → dispatch → invoice` ise yalnız shipped fulfillment olarak sayılır.

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
dispatch quantity - etkin posted invoice child line toplamı
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

- yalnız etkin child toplamları kaynak miktarı aşmamalı,
- cancelled + fulfilled toplamı quantity'yi aşmamalı,
- aynı dispatch miktarı toplam etkin invoice miktarından küçük olmamalı,
- source_line ancestry cycle içermemeli,
- order→proforma→invoice miktarı direct_invoiced olarak sayılmalı ve order kalanını azaltmalı,
- closed siparişte kullanılabilir kalan 0 olmalı.

Fark raporlanır; otomatik düzeltme yapılmaz.


## Faz 4 alış kısmi işlemi

K-088'e göre satınalma tarafı aynı source_line ancestry yaklaşımını kullanır.

Purchase order satırı:

```
ordered = quantity
cancelled = cancelled_quantity
received = etkin posted goods_receipt ancestry toplamı
direct_invoiced = purchase_order'a ulaşan ve zincirde goods_receipt bulunmayan etkin posted purchase_invoice toplamı
kalan = ordered - cancelled - received - direct_invoiced
```

Aynı miktar hem direct purchase_invoice hem goods_receipt ile ikinci kez karşılanamaz.

Goods receipt faturalanabilir miktarı:

```
receipt quantity - etkin posted purchase_invoice child toplamı
```

Bir goods_receipt birden fazla purchase_invoice'a bölünebilir. Aynı supplier + currency + uyumlu alış koşullarındaki birden fazla goods_receipt tek purchase_invoice'da birleşebilir.

Goods receipt stok etkisiz olsa da fulfillment hesabında gerçek teslim kaydıdır. Purchase invoice stok girişini fatura posting anında üretir.

Faz 4 yarış koşullarında purchase_order/goods_receipt source satırları transaction içinde yeniden okunur ve kilitlenir. `integrity:purchasing` bu miktar zincirini ayrıca doğrular.


## Dönem devrinde açık sipariş

K-256:

- yalnız `sales_order` ve `purchase_order` taşınır,
- source period belgesi/satırı değişmez,
- target order yalnız **kalan açık miktarı** taşır,
- target quantity yeni başlangıç miktarıdır; `cancelled_quantity=0`,
- target satır `source_line_id` ile eski period'a bağlanmaz,
- cross-period provenance `period_document_carries` ve satır scalar snapshot'ından okunur.

Sales order remaining, source period'da mevcut fulfillment formülüyle carry anında hesaplanır.

Purchase order remaining, source period'da mevcut alış fulfillment formülüyle carry anında hesaplanır.

Kalanı 0 olan satır target siparişe eklenmez; tüm satırları 0 kalan sipariş için target document oluşturulmaz.

Aktif sales-order reservation'ları source satırın kalan miktarı içinde location bazında target order satırına yeniden kurulur. Eski period reservation kaydı kopyalanmış business truth değildir; target'ta yeni reservation kaydı oluşur.
