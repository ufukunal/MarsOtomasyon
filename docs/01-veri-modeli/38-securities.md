# securities ve security_events

**Veritabanı: DÖNEM**

K-082, K-096 ve K-097 gereği çek/senet kıymetinin kimliği ile yaşam döngüsü olay geçmişi ayrı tutulur.

## securities

```php
Schema::connection('period')->create('securities', function (Blueprint $table) {
    $table->id();

    $table->string('security_type', 20); // check | promissory_note
    $table->string('direction', 10);     // received | issued

    $table->string('serial_no', 80);
    $table->decimal('amount', 18, 4);
    $table->char('currency', 3)->default('TRY');
    $table->date('due_date');

    $table->foreignId('original_contact_id')
        ->constrained('contacts')
        ->restrictOnDelete();

    $table->string('issuer_name')->nullable();
    $table->string('bank_name')->nullable();
    $table->string('branch_name')->nullable();

    $table->string('current_status', 30);

    $table->foreignId('current_bank_account_id')
        ->nullable()
        ->constrained('bank_accounts')
        ->restrictOnDelete();

    $table->string('settlement_reference', 100)->nullable();
    $table->date('bank_delivery_date')->nullable();

    $table->text('protest_or_return_details')->nullable();
    $table->jsonb('operation_metadata')->nullable();
    $table->text('notes')->nullable();

    $table->unsignedInteger('version')->default(1);

    $table->unsignedBigInteger('created_by')->nullable();
    $table->string('created_by_name')->nullable();

    $table->timestamps();

    $table->index(['security_type','direction','current_status']);
    $table->index(['due_date','current_status']);
    $table->index(['original_contact_id','due_date']);
});
```

## security_events

Yaşam döngüsü geçmişinin immutable kaynağıdır.

```php
Schema::connection('period')->create('security_events', function (Blueprint $table) {
    $table->id();

    $table->foreignId('security_id')
        ->constrained('securities')
        ->restrictOnDelete();

    $table->string('event_type', 30);
    $table->date('event_date');

    $table->foreignId('counterparty_contact_id')
        ->nullable()
        ->constrained('contacts')
        ->restrictOnDelete();

    $table->foreignId('bank_account_id')
        ->nullable()
        ->constrained('bank_accounts')
        ->restrictOnDelete();

    $table->string('reference', 100)->nullable();
    $table->text('description')->nullable();
    $table->jsonb('metadata')->nullable();

    $table->foreignId('contact_transaction_id')
        ->nullable()
        ->constrained('contact_transactions')
        ->restrictOnDelete();

    $table->foreignId('cash_movement_id')
        ->nullable()
        ->constrained('cash_movements')
        ->restrictOnDelete();

    $table->foreignId('bank_movement_id')
        ->nullable()
        ->constrained('bank_movements')
        ->restrictOnDelete();

    $table->foreignId('reversal_of_event_id')
        ->nullable()
        ->constrained('security_events')
        ->restrictOnDelete();

    $table->unsignedBigInteger('created_by')->nullable();
    $table->string('created_by_name')->nullable();
    $table->timestamps();

    $table->index(['security_id','event_date','id']);
});
```

## Durumlar

### Received

- `received`
- `portfolio`
- `endorsed`
- `sent_to_collection`
- `collected`
- `bounced_or_returned`

### Issued

- `issued`
- `awaiting_payment`
- `paid`
- `returned_or_cancelled`

`current_status`, son etkin event'ten türetilen snapshot'tır. `integrity:securities` bunu event geçmişiyle doğrular.

## Event tipleri

En az:

- `receive`
- `move_to_portfolio`
- `endorse`
- `send_to_collection`
- `collect`
- `bounce_or_return`
- `issue`
- `mark_awaiting_payment`
- `pay`
- `return_or_cancel`
- `reverse`

Event silinmez/değiştirilmez.

## Cari etkisi referansı

K-082:

- received ilk teslim: original contact için `credit`,
- issued ilk teslim: original contact için `debit`,
- collect/pay: yeni cari hareket yok,
- bounce/return: ilgili önceki cari etkisinin exact inverse hareketi,
- endorse: original contact ikinci kez etkilenmez; ciro hedef contact için cari etkisi oluşur.

Cari etkili event kendi `contact_transaction_id` referansını taşır.

## Ciro

`endorse` event'inde:

- `counterparty_contact_id` zorunlu,
- event_date zorunlu,
- original_contact_id değiştirilmez,
- kıymet kimliği değişmez,
- history korunur.

## Banka operasyon alanları

K-097 gereği:

- banka hesap bağlantısı,
- banka teslim tarihi,
- tahsil/ödeme referansı,
- protesto/karşılıksız detayları,
- operation metadata

desteklenir.

Bu alanlar genel muhasebe hesabı üretmez; yalnız ön muhasebe operasyon kaydıdır.

## Risk

K-078 için portföy riski `security_events` + `current_status` üzerinden hesaplanır. Hangi status'ların riskte sayıldığı iş kuralı 38'de kanonik olarak tanımlanır.

## Bütünlük

`integrity:securities`:

- status/event zinciri,
- direction'a göre izinli geçiş,
- cari etkili event ↔ contact_transaction yön/tutarı,
- collect/pay aşamasında ikinci cari hareket olmaması,
- ciroda original contact'ın ikinci kez etkilenmemesi,
- counterparty zorunluluğu,
- reversal event bağlantısı,
- current_status snapshot

kontrollerini yapar.
