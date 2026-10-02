# purchase_quote_selections

**Veritabanı: DÖNEM**

K-090/K-091 gereği satınalma teklif toplamada hem belge bazlı hem satır bazlı seçim vardır. Seçim otomatik değildir; yetkili kullanıcı yapar ve audit edilir.

Bu tablo yalnız **hangi tedarikçi teklif satırının hangi satınalma talep satırı için seçildiğini** kalıcı olarak tutar. Teklif fiyatları veya miktarları burada kopyalanmaz; gerçek kaynak `document_lines` satırlarıdır.

## Şema

```php
Schema::connection('period')->create('purchase_quote_selections', function (Blueprint $table) {
    $table->id();

    $table->foreignId('request_line_id')
        ->constrained('document_lines')
        ->restrictOnDelete();

    $table->foreignId('supplier_quote_line_id')
        ->constrained('document_lines')
        ->restrictOnDelete();

    $table->unsignedBigInteger('selected_by')->nullable();
    $table->string('selected_by_name')->nullable();
    $table->timestamp('selected_at');

    $table->unsignedInteger('version')->default(1);
    $table->timestamps();

    $table->unique('request_line_id');
    $table->unique(
        ['request_line_id','supplier_quote_line_id'],
        'purchase_quote_selection_pair_unique'
    );
});
```

Period tablosudur; `company_id` yoktur. Master user'a gerçek FK kurulmaz.

## Doğrulama

Uygulama katmanı seçimden önce:

- request_line parent document type = `purchase_request`,
- supplier_quote_line parent document type = `supplier_quote`,
- supplier quote line ancestry aynı request line'a ulaşır,
- iki satır aynı product_id için geçerlidir,
- teklif belge durumu seçime uygundur,
- actor `purchasing.quote.select` iznine sahiptir

kontrollerini yapar.

DB düzeyinde doğrudan cross-row document_type CHECK kurulmaz; bu ilişki Action + gerçek FK + bütünlük kontrolüyle korunur.

## Belge bazlı seçim

Belge bazlı tek teklif seçimi ayrı bir "winner_document_id" alanı değildir.

Kullanıcı bir tedarikçi teklifini belge bazında seçtiğinde sistem teklifin uygun her satırı için `purchase_quote_selections` kaydı üretir. Böylece belge ve satır bazlı seçim aynı gerçek kaynağı kullanır.

## Satır bazlı seçim

Her request line için en fazla bir aktif seçim vardır. Farklı request line'lar farklı supplier quote line'lara bağlanabilir.

Satınalma siparişi üretiminde seçili satırlar tedarikçi contact_id'ye göre gruplanır; her tedarikçi için ayrı purchase_order oluşturulur.

## Seçim değişikliği

Siparişe dönüştürülmemiş seçim, optimistic lock ile değiştirilebilir. Değişiklik:

- eski seçim,
- yeni seçim,
- actor,
- timestamp

ile period audit'e yazılır.

Seçimden purchase_order satırı üretildikten sonra o request line için seçim yerinde değiştirilmez; düzeltme yeni satınalma süreciyle yapılır.

## Bütünlük

`integrity:purchasing`:

- request_line gerçekten purchase_request satırı mı,
- supplier_quote_line gerçekten supplier_quote satırı mı,
- source lineage aynı request satırına ulaşıyor mu,
- aynı request_line için birden fazla seçim var mı,
- seçime bağlı oluşturulmuş purchase_order satırları doğru supplier contact'a mı ait

kontrollerini yapar.

Fark otomatik düzeltilmez.
