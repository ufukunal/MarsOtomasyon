# documents

**Veritabanı: DÖNEM**

Faz 3'te kurulan genel belge başlık tablosudur. Satış belgeleri burada başlar; Faz 4 ve sonraki belge türleri aynı tabloyu kullanabilir. Period DB fiziksel olarak şirkete/yıla ait olduğu için `company_id` yoktur.

## Şema

```php
Schema::connection('period')->create('documents', function (Blueprint $table) {
    $table->id();

    $table->string('document_type', 40);
    $table->string('number', 40)->nullable();
    $table->unsignedSmallInteger('revision_no')->default(0); // K-074
    $table->date('document_date');
    $table->date('due_date')->nullable();
    $table->date('valid_until')->nullable(); // teklif/proforma geçerliliği

    $table->foreignId('contact_id')->nullable()->constrained('contacts');

    $table->char('currency', 3)->default('TRY');
    $table->decimal('exchange_rate', 18, 6)->default(1);

    $table->string('status', 30)->default('draft');

    $table->decimal('discount_rate', 7, 4)->default(0);
    $table->decimal('discount_amount', 18, 4)->default(0);
    $table->decimal('subtotal', 18, 4)->default(0);
    $table->decimal('tax_base', 18, 4)->default(0);
    $table->decimal('vat_amount', 18, 4)->default(0);
    $table->decimal('rounding_difference', 18, 4)->default(0);
    $table->decimal('grand_total', 18, 4)->default(0);

    $table->jsonb('requirements_snapshot')->nullable();
    $table->text('notes')->nullable();

    $table->unsignedInteger('version')->default(1);

    // Master users'a cross-DB FK YOK
    $table->unsignedBigInteger('created_by')->nullable();
    $table->string('created_by_name')->nullable();
    $table->unsignedBigInteger('posted_by')->nullable();
    $table->string('posted_by_name')->nullable();
    $table->timestamp('posted_at')->nullable();

    $table->timestamps();

    $table->unique(
        ['document_type', 'number', 'revision_no'],
        'documents_number_revision_unique'
    );
    $table->index(['contact_id', 'document_date']);
    $table->index(['document_type', 'status', 'document_date']);
});
```

## Faz 3 belge türleri

- `quote`
- `sales_order`
- `dispatch`
- `sales_invoice`
- `proforma`
- `collection`
- `contact_debit_credit`

## Faz 4 belge türleri

- `purchase_request`
- `supplier_quote`
- `purchase_order`
- `goods_receipt`
- `purchase_invoice`

## Faz 5 belge türleri

- `supplier_payment`
- `finance_transfer`
- `cash_count_adjustment`

Bu Faz 5 tipleri `header_amount` hesap modundadır; document_lines gerektirmez.

## Faz 6 belge türleri

- `sales_return`
- `purchase_return`

Faz 6 iade belgeleri satırlıdır ve ortak document_lines yapısını kullanır.

Belge tipi string tutulur; enum/sözleşme uygulama katmanında bu türleri doğrular.

## Kurallar

- Taslak belgede `number = null` olabilir.
- Numara yalnız ilgili yaşam döngüsü geçişinde `GenerateDocumentNumber` ile ve `lockForUpdate` altında üretilir.
- Teklif revizyonları ayrı kayıtlar olup aynı ana numarayı ve farklı `revision_no` değerini taşır. **İlk teklif revizyonu `revision_no = 1`'dir; `Rev.0` yoktur.** Generic kolon default 0 yalnız revizyon kullanmayan diğer belge türleri içindir.
- Satış tarafında K-013 gereği para birimi TRY'dir. Faz 4 alış belgelerinde döviz kullanılabilir; `currency + exchange_rate` kesinleşmede snapshot olarak donar.
- `document_date` iş tarihidir; dönem kilidi ve raporlar bunu kullanır.
- Posted/kesinleşmiş kayıt yerinde değiştirilmez.
- `version` yalnız düzenlenebilir durumlarda optimistic lock için kullanılır.
- `requirements_snapshot`, teklif/sipariş bağlamındaki dondurulmuş müşteri/konfigürasyon ihtiyacını saklayabilir; güncel kart sonradan değişse de snapshot değişmez.
- Belge toplamları `Money`/BCMath ile hesaplanır; float kullanılmaz.
- `purchase_request` için contact_id null olabilir; supplier_quote, purchase_order, goods_receipt ve purchase_invoice için supplier contact zorunludur.
- K-087 gereği goods_receipt stok/cari posting üretmez; purchase_invoice stok in + supplier credit üretir.
- K-093 supplier_payment supplier contact debit + tek finans out üretir.
- K-092 finance_transfer contact_id kullanmaz; source out + target in üretir.
- K-094 cash_count_adjustment yalnız onaylı kasa sayım farkından üretilir.
- K-100 sales_return customer credit + stock in + quarantine üretir.
- K-101 purchase_return supplier debit + stock out üretir.
- K-105 iade belgeleri otomatik cash/bank hareketi üretmez.

## CHECK kısıtları

- `exchange_rate > 0`
- `discount_rate between 0 and 100`
- `discount_amount >= 0`
- `subtotal >= 0`
- `tax_base >= 0`
- `vat_amount >= 0`
- `grand_total >= 0`
- `document_type <> 'quote' OR revision_no >= 1`
- `abs(grand_total - (tax_base + vat_amount + rounding_difference)) < 0.0001`

## İlişkiler

- `contact_id -> contacts.id` aynı period DB'de gerçek FK.
- Satırlar `document_lines.document_id` ile bağlanır.
- Belge dönüşümleri `document_relations` ile tutulur.
- Cari etkisi varsa en fazla bir `contact_transactions.document_id` kaydı oluşur.
