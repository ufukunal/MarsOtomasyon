# Faz 9 — E-ticaret kanal veri modeli

Faz 9 iki fiziksel katman kullanır:

- **Master DB:** kanal hesabı/credential ve dönemler arası external-event duplicate registry.
- **Period DB:** ürün listing mapping, kanal-location kapsamı, dönem sipariş snapshot'ları ve sync history.

## Master — sales_channel_accounts

Bir şirkette aynı platformdan birden fazla hesap olabilir.

```php
Schema::connection('master')->create('sales_channel_accounts', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();

    $table->string('platform', 30);
    // trendyol | hepsiburada | n11 | woocommerce

    $table->string('name');
    $table->string('external_store_id')->nullable();

    $table->text('credentials_encrypted');
    $table->jsonb('settings')->nullable();

    $table->boolean('is_active')->default(true);
    $table->unsignedInteger('version')->default(1);

    $table->timestamps();

    $table->index(['company_id','platform','is_active']);
});
```

Credential plaintext DB'de tutulmaz.

## Master — channel_external_event_registry

K-197 dönemler arası duplicate engelinin gerçek kaynağıdır.

```php
Schema::connection('master')->create('channel_external_event_registry', function (Blueprint $table) {
    $table->id();

    $table->foreignId('channel_account_id')
        ->constrained('sales_channel_accounts')
        ->cascadeOnDelete();

    $table->string('event_type', 30);
    // order | cancel | return

    $table->string('external_id', 120);

    $table->unsignedBigInteger('period_id')->nullable();
    $table->unsignedBigInteger('period_document_id')->nullable();

    $table->timestamp('external_occurred_at')->nullable();
    $table->string('status', 20); // processing | done | failed

    $table->timestamps();

    $table->unique(
        ['channel_account_id','event_type','external_id'],
        'channel_external_event_unique'
    );
});
```

Period belge id alanı scalar provenance'dır; cross-DB FK değildir.

## Period — channel_account_period_settings

K-182'nin period-level gerçek eşlemesidir. Channel account Master DB'de, marketplace customer contact ise period DB'de olduğu için cross-DB FK kurulmaz.

```php
Schema::connection('period')->create('channel_account_period_settings', function (Blueprint $table) {
    $table->id();

    $table->unsignedBigInteger('channel_account_id'); // Master scalar

    $table->foreignId('marketplace_customer_contact_id')
        ->constrained('contacts')
        ->restrictOnDelete();

    $table->unsignedInteger('version')->default(1);
    $table->timestamps();

    $table->unique('channel_account_id');
});
```

Her channel account için ilgili period'da tek marketplace customer contact eşlemesi vardır. Dönem devrinde contact ID korunarak bu mapping yeni period'a taşınır.

## Period — channel_product_listings

```php
Schema::connection('period')->create('channel_product_listings', function (Blueprint $table) {
    $table->id();

    $table->unsignedBigInteger('channel_account_id'); // Master scalar
    $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

    $table->string('external_product_id')->nullable();
    $table->string('external_listing_id')->nullable();
    $table->string('external_sku')->nullable();

    $table->string('stock_mode', 20)->nullable();
    // null => products.channel_stock_mode
    // stock | production | manual

    $table->decimal('max_channel_quantity', 18, 3)->nullable();
    $table->decimal('withhold_quantity', 18, 3)->default(0);
    $table->decimal('fixed_quantity', 18, 3)->nullable();
    $table->decimal('manual_quantity', 18, 3)->nullable();

    $table->unsignedInteger('lead_time_days')->nullable();

    $table->decimal('price_override', 18, 4)->nullable();
    $table->string('title_override')->nullable();
    $table->text('description_override')->nullable();

    $table->string('image_collection')->nullable();
    $table->jsonb('category_metadata')->nullable();

    $table->boolean('is_active')->default(true);
    $table->unsignedInteger('version')->default(1);

    $table->timestamps();

    $table->unique(['channel_account_id','product_id']);
    $table->index(['channel_account_id','external_listing_id']);
});
```

Her internal varyant ayrı product olduğundan listing de ayrı mapping'dir.

## Period — channel_listing_locations

K-174 gereği stock mode tüm depoları değil seçilmiş location kapsamını kullanır.

```php
Schema::connection('period')->create('channel_listing_locations', function (Blueprint $table) {
    $table->id();

    $table->foreignId('channel_product_listing_id')
        ->constrained('channel_product_listings')
        ->cascadeOnDelete();

    $table->foreignId('location_id')
        ->constrained('locations')
        ->restrictOnDelete();

    $table->timestamps();

    $table->unique(['channel_product_listing_id','location_id']);
});
```

Subcontractor location bu tabloya eklenemez.

## Period — channel_order_snapshots

Imported sales_order'a ait external buyer/shipping/channel metadata.

```php
Schema::connection('period')->create('channel_order_snapshots', function (Blueprint $table) {
    $table->id();

    $table->foreignId('sales_order_id')
        ->constrained('documents')
        ->restrictOnDelete();

    $table->unsignedBigInteger('channel_account_id');
    $table->string('external_order_id', 120);
    $table->string('external_order_no')->nullable();

    $table->string('buyer_name')->nullable();
    $table->string('recipient_name')->nullable();
    $table->string('phone')->nullable();
    $table->string('email')->nullable();

    $table->text('address')->nullable();
    $table->string('city')->nullable();
    $table->string('district')->nullable();
    $table->string('postcode')->nullable();

    $table->string('cargo_company')->nullable();
    $table->string('cargo_code')->nullable();
    $table->string('external_shipment_id')->nullable();
    $table->string('external_package_id')->nullable();

    $table->jsonb('campaign_metadata')->nullable();

    $table->timestamps();

    $table->unique('sales_order_id');
    $table->unique(['channel_account_id','external_order_id']);
});
```

Sipariş satırlarının fiyat/iskonto değerleri mevcut document_lines frozen alanlarına normalize edilir.

## Period — channel_sync_events

```php
Schema::connection('period')->create('channel_sync_events', function (Blueprint $table) {
    $table->id();

    $table->unsignedBigInteger('channel_account_id');
    $table->string('direction', 10); // outbound | inbound
    $table->string('entity_type', 30);
    $table->unsignedBigInteger('entity_id')->nullable();
    $table->string('external_id')->nullable();

    $table->string('action', 40);
    $table->string('status', 20); // queued | processing | success | failed
    $table->unsignedSmallInteger('attempts')->default(0);

    $table->string('correlation_id', 80)->nullable();
    $table->string('payload_hash', 128)->nullable();
    $table->text('error_summary')->nullable();

    $table->timestamp('last_attempt_at')->nullable();
    $table->timestamps();

    $table->index(['channel_account_id','status','created_at']);
});
```

Hassas tam API payload saklanmaz.

## Period — channel_sync_errors

Kalıcı operasyonel hata kuyruğudur.

```php
Schema::connection('period')->create('channel_sync_errors', function (Blueprint $table) {
    $table->id();

    $table->foreignId('channel_sync_event_id')
        ->constrained('channel_sync_events')
        ->cascadeOnDelete();

    $table->text('error_summary');
    $table->timestamp('resolved_at')->nullable();
    $table->unsignedBigInteger('resolved_by')->nullable();
    $table->string('resolved_by_name')->nullable();

    $table->timestamps();
});
```

## Dönem devri

Taşınır:

- channel_account_period_settings,
- channel_product_listings,
- channel_listing_locations,
- listing external ids,
- listing override/settings.

Taşınmaz:

- channel_order_snapshots,
- channel_sync_events,
- channel_sync_errors.

Master sales_channel_accounts ve channel_external_event_registry zaten yıl bağımsızdır.

## Integrity

- `integrity:channels`
- `integrity:channel-orders`

en az listing/location uygunluğu, duplicate external order, mapping ve sync provenance tutarlılığını kontrol eder.
