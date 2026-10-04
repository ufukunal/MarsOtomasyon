# cash_counts

**Veritabanı: DÖNEM**

K-094 gereği kasa sayımı toplam fiili bakiye üzerinden yapılır. Kupür detayı tutulmaz.

## Şema

```php
Schema::connection('period')->create('cash_counts', function (Blueprint $table) {
    $table->id();

    $table->foreignId('cash_account_id')
        ->constrained('cash_accounts')
        ->restrictOnDelete();

    $table->date('count_date');

    $table->decimal('system_balance', 18, 4);
    $table->decimal('actual_balance', 18, 4);
    $table->decimal('difference', 18, 4);

    $table->string('status', 20)->default('draft');
    $table->text('reason')->nullable();

    $table->foreignId('adjustment_document_id')
        ->nullable()
        ->constrained('documents')
        ->restrictOnDelete();

    $table->unsignedInteger('version')->default(1);

    $table->unsignedBigInteger('created_by')->nullable();
    $table->string('created_by_name')->nullable();
    $table->unsignedBigInteger('confirmed_by')->nullable();
    $table->string('confirmed_by_name')->nullable();
    $table->timestamp('confirmed_at')->nullable();

    $table->timestamps();

    $table->index(['cash_account_id','count_date']);
});
```

Period tablosudur; `company_id` yoktur. Master user alanlarına cross-DB FK kurulmaz.

## Değerler

`system_balance`, sayım ekranı açıldığında değil **confirm transaction'ında** ilgili kasanın hareket toplamından yeniden hesaplanıp snapshot edilir:

```
system_balance = SUM(in) - SUM(out)
difference = actual_balance - system_balance
```

Tutarlar Money/BCMath ile hesaplanır.

## Durum

- `draft`
- `confirmed`

Draft optimistic lock ile düzenlenebilir.

Confirmed kayıt immutable'dır.

## Fark

- difference = 0 ise adjustment_document_id null kalır.
- difference != 0 ise reason zorunludur.
- kullanıcı onayında `cash_count_adjustment` document + tek cash movement aynı transaction içinde üretilir.
- geçmiş cash movement kayıtları değiştirilmez.

## CHECK / doğrulama

- actual_balance >= 0
- status yalnız draft|confirmed
- confirmed ise confirmed_at dolu
- difference != 0 ve confirmed ise adjustment_document_id zorunlu
- difference = actual_balance - system_balance post-write verify ile doğrulanır

## Audit

Confirm audit'i:

- cash account,
- system balance,
- actual balance,
- difference,
- reason,
- actor

snapshot'ını taşır.
