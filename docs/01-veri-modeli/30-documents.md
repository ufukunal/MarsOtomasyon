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

Belge tipi string tutulur; sonraki fazlar aynı tabloya yeni tür ekleyebilir.

## Kurallar

- Taslak belgede `number = null` olabilir.
- Numara yalnız ilgili yaşam döngüsü geçişinde `GenerateDocumentNumber` ile ve `lockForUpdate` altında üretilir.
- Teklif revizyonları ayrı kayıtlar olup aynı ana numarayı ve farklı `revision_no` değerini taşır.
- Satış tarafında K-013 gereği para birimi TRY'dir; generic kolonlar Faz 4 alış/ithalat için korunur.
- `document_date` iş tarihidir; dönem kilidi ve raporlar bunu kullanır.
- Posted/kesinleşmiş kayıt yerinde değiştirilmez.
- `version` yalnız düzenlenebilir durumlarda optimistic lock için kullanılır.
- `requirements_snapshot`, teklif/sipariş bağlamındaki dondurulmuş müşteri/konfigürasyon ihtiyacını saklayabilir; güncel kart sonradan değişse de snapshot değişmez.
- Belge toplamları `Money`/BCMath ile hesaplanır; float kullanılmaz.

## CHECK kısıtları

- `exchange_rate > 0`
- `discount_rate between 0 and 100`
- `discount_amount >= 0`
- `subtotal >= 0`
- `tax_base >= 0`
- `vat_amount >= 0`
- `grand_total >= 0`
- `abs(grand_total - (tax_base + vat_amount + rounding_difference)) < 0.0001`

## İlişkiler

- `contact_id -> contacts.id` aynı period DB'de gerçek FK.
- Satırlar `document_lines.document_id` ile bağlanır.
- Belge dönüşümleri `document_relations` ile tutulur.
- Cari etkisi varsa en fazla bir `contact_transactions.document_id` kaydı oluşur.
