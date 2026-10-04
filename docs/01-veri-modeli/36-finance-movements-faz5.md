# Faz 5 finans hareketleri — cash/bank genişletmesi

**Veritabanı: DÖNEM**

Faz 3'te K-075 için kurulan `cash_accounts`, `bank_accounts`, `cash_movements`, `bank_movements` tabloları korunur. Faz 5 bunları K-092…K-095 için genişletir; ikinci bir kasa/banka hareket ailesi oluşturulmaz.

## cash_movements değişikliği

Faz 3'teki `unique(document_id)` kaldırılır. K-092 gereği aynı virman belgesi iki ayrı kasa hareketi üretebilir.

Yerine:

```php
$table->unique(
    ['document_id','cash_account_id','direction'],
    'cash_movements_document_account_direction_unique'
);
```

eklenir.

Bir document için aynı kasa + aynı yönde ikinci hareket üretilemez; fakat Kasa→Kasa virmanda kaynak `out`, hedef `in` farklı hesaplarda tek document altında tutulabilir.

## bank_movements değişikliği

Faz 3'teki `unique(document_id)` kaldırılır.

Yerine:

```php
$table->unique(
    ['document_id','bank_account_id','direction'],
    'bank_movements_document_account_direction_unique'
);

$table->boolean('is_reconciled')->nullable();
$table->timestamp('reconciled_at')->nullable();
$table->unsignedBigInteger('reconciled_by')->nullable();
$table->string('reconciled_by_name')->nullable();
$table->unsignedInteger('version')->default(1);
```

eklenir.

`is_reconciled`:

- `null`: henüz manuel değerlendirilmemiş,
- `true`: mutabık,
- `false`: mutabık değil.

Bu alan finansal tutarı değiştirmez; yalnız K-095 manuel mutabakat metadata'sıdır.

## Faz 5 finans document type'ları

Ortak `documents` tablosu şu yeni tipleri kullanır:

- `supplier_payment`
- `finance_transfer`
- `cash_count_adjustment`

Bu belgeler `header_amount` hesap modunu kullanır; document_lines gerektirmez.

## supplier_payment

K-093:

- contact_id zorunlu supplier,
- grand_total ödeme tutarıdır,
- kaynak alış faturası zorunlu değildir,
- seçilmişse `document_relations.payment_source` bilgi amaçlıdır,
- bir cash veya bank `out` hareketi üretir,
- bir `contact_transactions.debit` üretir.

## finance_transfer

K-092:

- contact_id null,
- source ve target hesap hareket satırlarından anlaşılır,
- aynı currency zorunlu,
- aynı fiziksel hesap source+target olamaz,
- tek document iki finans hareketinin ortak kimliğidir.

Etki:

- source: `out`
- target: `in`

Cari hareket üretmez.

## cash_count_adjustment

K-094 kapsamındaki sayım farkı onaylandığında oluşur.

- contact_id null,
- tek cash account hareketi üretir,
- fiili > sistem ise `in`,
- fiili < sistem ise `out`,
- amount = mutlak fark,
- açıklama sayım kaydının gerekçesini taşır.

## Para birimi

K-092 gereği virman source/target hesap currency değerleri aynı olmalıdır.

Faz 5 virmanında:

- döviz dönüşümü yok,
- kur snapshot yok,
- kur farkı yok.

Supplier payment seçilen finans hesabının currency'si ile uyumlu olmalıdır. K-013 dışına çıkacak yeni döviz ödeme davranışı ayrıca karar verilmeden eklenmez.

## Bütünlük

`integrity:finance` en az:

- finance_transfer için tam iki finans hareketi,
- source out + target in,
- tutar eşitliği,
- currency eşitliği,
- source != target,
- supplier_payment için tek finans out + tek contact debit,
- cash_count_adjustment için tek cash movement,
- reconciliation metadata actor/timestamp tutarlılığı

kontrollerini yapar.

Fark otomatik düzeltilmez.
