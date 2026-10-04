# document_lines

**Veritabanı: DÖNEM**

Belge satırı kullanıcının seçtiği birimi/fiyatı ve posting sırasında gerekli dondurulmuş temel birim karşılığını birlikte saklar.

## Şema

```php
Schema::connection('period')->create('document_lines', function (Blueprint $table) {
    $table->id();
    $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
    $table->unsignedInteger('line_no');
    $table->string('line_kind', 20)->default('stock'); // stock | service

    $table->foreignId('product_id')->nullable()->constrained('products');
    $table->string('description')->nullable();

    $table->foreignId('unit_id')->nullable()->constrained('units');
    $table->decimal('quantity', 18, 3);
    $table->decimal('conversion_factor', 18, 6)->nullable();
    $table->decimal('base_quantity', 18, 3)->nullable();

    $table->foreignId('location_id')->nullable()->constrained('locations');

    $table->decimal('unit_price', 18, 4); // seçilen birim için, KDV hariç
    $table->decimal('line_discount_rate', 7, 4)->default(0);
    $table->decimal('line_discount_amount', 18, 4)->default(0);
    $table->decimal('vat_rate', 7, 4)->default(0);
    $table->decimal('line_total', 18, 4);

    $table->boolean('reserve_stock')->default(false);
    $table->decimal('cancelled_quantity', 18, 3)->default(0);

    $table->jsonb('configuration')->nullable();

    $table->foreignId('source_line_id')
        ->nullable()
        ->constrained('document_lines')
        ->nullOnDelete();

    $table->unsignedInteger('version')->default(1);
    $table->timestamps();

    $table->unique(['document_id','line_no']);
    $table->index('source_line_id');
});
```

## Satır türü

K-257:

### stock

- `product_id`, `unit_id`, `conversion_factor`, `base_quantity` zorunludur.
- stok hareketi ve moving-average etkisi belge tipinin posting profilinden doğar.
- mevcut temel birim kuralları aynen uygulanır.

### service

- aynı purchase_invoice içinde mal satırlarıyla birlikte bulunabilir,
- `product_id`, `unit_id`, `conversion_factor`, `base_quantity` null olabilir,
- `quantity > 0`, `unit_price`, iskonto ve KDV alanları ticari hesap için kullanılmaya devam eder,
- cari/KDV/belge toplamına girer,
- stock movement, reservation ve moving-average üretmez,
- `location_id` null olmalıdır.

## Temel birim

```
base_quantity = quantity × conversion_factor
```

- `quantity`: stock satırda seçilen birimde; service satırda ticari hizmet miktarıdır.
- `unit_price`: satırın seçilen/ticari miktarına ait fiyattır.
- `base_quantity`: yalnız stock satırda stok hareketine gidecek miktardır.
- `conversion_factor`: belge anındaki katsayı; dondurulur.
- Dönüşüm bulunamazsa posting engellenir; 1 varsayılmaz.

## Satır iskontosu

K-080 gereği hem yüzde hem tutar saklanır. Kullanıcı birini değiştirince diğeri hesaplanır; kesinleşen belgede ikisi de snapshot'tır.

```
gross = quantity × unit_price
line_total = gross - line_discount_amount
```

Ara hesapta 2 hanelik half-up yapılmaz. `line_total` 4 hanelik para snapshot'ı olarak saklanır; K-036'daki 2 hane half-up yalnız KDV grup sonucu ve belge grand total seviyesindedir.

## Kısmi işlem

- `source_line_id`, teklif→sipariş, sipariş→irsaliye/fatura, teklif/sipariş→proforma→fatura ve irsaliye→fatura gibi satır kaynak zincirini tutar.
- Fulfillment hesabı yalnız bir parent seviyesi okumaz; parent zinciri cycle guard ile köke kadar izlenir. `order→proforma→invoice` order direct fulfillment sayılır; `order→dispatch→invoice` shipped fulfillment'ın üstüne ikinci kez eklenmez.
- Sevk edilmiş ve faturalanmış miktar ayrı kolon olarak kopyalanmaz; kaynak satıra bağlı hedef satırların toplamından hesaplanır.
- `cancelled_quantity` kalıcıdır.
- Kullanılabilir kalan: `quantity - shipped - cancelled` veya ilgili akışta kaynak satırın kalan miktarı.
- İptal edilen miktar yeniden rezerv/sevk/fatura edilemez.

## Lokasyon

K-073 gereği satış satırı lokasyon taşıyabilir. Sipariş rezervasyonu bir satırı birden fazla lokasyona bölerse gerçek dağılım `stock_reservations` kayıtlarındadır; sevk belgesi satırları ilgili lokasyonlarla üretilir.

## CHECK kısıtları

- `line_kind in stock|service`
- `quantity > 0`
- stock satırda: product_id/unit_id zorunlu, conversion_factor > 0, base_quantity > 0
- service satırda: conversion_factor/base_quantity/location_id null; product_id/unit_id opsiyonel
- `unit_price >= 0`
- `line_discount_rate between 0 and 100`
- `line_discount_amount >= 0`
- `vat_rate >= 0`
- `cancelled_quantity >= 0 and cancelled_quantity <= quantity`
- `source_line_id IS NULL OR source_line_id <> id` (doğrudan self-cycle engeli; daha uzun cycle Action/lineage resolver tarafından reddedilir)
