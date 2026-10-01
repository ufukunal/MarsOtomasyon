# Faz 3 minimum kasa / banka altyapısı

**Veritabanı: DÖNEM**

K-075 gereği tahsilatın gerçek bir kasa/banka hesabına bağlanabilmesi için Faz 5'in yalnız minimum kısmı Faz 3'e çekilir. Virman, ekstre, mutabakat, kasa sayımı ve tam çek/senet modülü Faz 5'te kalır.

## cash_accounts

```php
Schema::connection('period')->create('cash_accounts', function (Blueprint $table) {
    $table->id();
    $table->string('code', 30)->unique();
    $table->string('name');
    $table->char('currency', 3)->default('TRY');
    $table->boolean('is_active')->default(true);
    $table->unsignedInteger('version')->default(1);
    $table->timestamps();
});
```

v65 alanları: Kasa Kodu, Kasa Adı, Para Birimi, Durum.

## bank_accounts

```php
Schema::connection('period')->create('bank_accounts', function (Blueprint $table) {
    $table->id();
    $table->string('code', 30)->unique();
    $table->string('bank_name');
    $table->string('account_name');
    $table->string('iban', 34)->nullable();
    $table->char('currency', 3)->default('TRY');
    $table->boolean('is_active')->default(true);
    $table->unsignedInteger('version')->default(1);
    $table->timestamps();
});
```

v65 alanları: Banka, Hesap, IBAN, Para Birimi.

## cash_movements

```php
Schema::connection('period')->create('cash_movements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('cash_account_id')->constrained('cash_accounts');
    $table->foreignId('document_id')->nullable()->constrained('documents')->restrictOnDelete();
    $table->foreignId('contact_id')->nullable()->constrained('contacts');

    $table->date('movement_date');
    $table->string('direction', 3); // in | out
    $table->decimal('amount', 18, 4);
    $table->text('description')->nullable();

    $table->unsignedBigInteger('created_by')->nullable();
    $table->string('created_by_name')->nullable();
    $table->timestamps();

    $table->index(['cash_account_id','movement_date']);
    $table->unique('document_id');
});
```

## bank_movements

```php
Schema::connection('period')->create('bank_movements', function (Blueprint $table) {
    $table->id();
    $table->foreignId('bank_account_id')->constrained('bank_accounts');
    $table->foreignId('document_id')->nullable()->constrained('documents')->restrictOnDelete();
    $table->foreignId('contact_id')->nullable()->constrained('contacts');

    $table->date('movement_date');
    $table->string('direction', 3); // in | out
    $table->decimal('amount', 18, 4);
    $table->string('reference', 80)->nullable();
    $table->text('description')->nullable();

    $table->unsignedBigInteger('created_by')->nullable();
    $table->string('created_by_name')->nullable();
    $table->timestamps();

    $table->index(['bank_account_id','movement_date']);
    $table->unique('document_id');
});
```

## CHECK

- movement amount > 0
- direction in ('in','out')

## Faz 3 sınırı

Tahsilat posting'i:

- `contact_transactions` credit hareketi,
- ödeme yöntemi nakitse `cash_movements.in`,
- ödeme yöntemi banka ise `bank_movements.in`

üretir. Hepsi aynı transaction içindedir.

Faz 3'te virman, banka ekstresi importu, mutabakat, kasa sayımı ve çek/senet yaşam döngüsü yapılmaz.


## Kart düzenleme / dönem devri

- `cash_accounts` ve `bank_accounts` karttır; fiziksel silinmez, pasife alınır ve `version` optimistic lock kullanır.
- Aynı şirket dönem devrinde taşınan kasa/banka kartlarının ID ve kodları korunur.
- Geçmiş `cash_movements` / `bank_movements` kopyalanmaz; kapanış bakiyesi yeni dönemde açılış hareketi olarak yazılır.
