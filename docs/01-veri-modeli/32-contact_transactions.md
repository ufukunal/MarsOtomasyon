# contact_transactions

**Veritabanı: DÖNEM**

Cari bakiyenin tek gerçek kaynağıdır (K-062). Fatura bazlı tahsilat kapatma tablosu değildir.

## Şema

```php
Schema::connection('period')->create('contact_transactions', function (Blueprint $table) {
    $table->id();

    $table->foreignId('contact_id')->constrained('contacts');
    $table->foreignId('document_id')
        ->nullable()
        ->constrained('documents')
        ->restrictOnDelete();

    $table->string('transaction_type', 30);
    $table->string('direction', 6); // debit | credit
    $table->date('transaction_date');
    $table->date('due_date')->nullable();

    $table->decimal('amount', 18, 4);
    $table->char('currency', 3)->default('TRY');

    $table->foreignId('reversal_of_id')
        ->nullable()
        ->constrained('contact_transactions')
        ->restrictOnDelete();

    $table->text('description')->nullable();

    // Master user snapshot, FK YOK
    $table->unsignedBigInteger('created_by')->nullable();
    $table->string('created_by_name')->nullable();

    $table->timestamps();

    $table->unique('document_id');
    $table->index(['contact_id','transaction_date']);
    $table->index(['contact_id','due_date']);
});
```

## Yön

Tek formül tüm cari türleri için korunur:

- `debit`: bakiyeyi artırır,
- `credit`: bakiyeyi azaltır.

```
bakiye = SUM(debit amount) - SUM(credit amount)
```

Müşteri satış faturası `debit`, tahsilat `credit` üretir.

K-087 gereği tedarikçiye borç doğuran Faz 4 alış faturası `credit` üretir. Böylece tedarikçi borcu bu formülde negatif bakiye yönünde görünür.

K-013 ile dövizli alış mümkün olduğundan tek bakiye toplamında farklı para birimleri doğrudan toplanmaz. Faz 4 purchase_invoice cari hareketi şirket temel para birimine çevrilmiş tutarı yazar:

```
ledger_amount = document.grand_total × frozen exchange_rate
currency = company.base_currency  // mevcut varsayılan TRY
```

Orijinal döviz tutarı/currency/exchange_rate `documents` üzerinde snapshot olarak kalır. Kur farkı hesabı yapılmaz.

Faz 4'te tedarikçi ödeme hareketi yoktur; ödeme Faz 5 kapsamındadır.

## Bakiye

Bakiye contacts üzerinde saklanmaz. Stok bakiyesi gibi cari bakiyesi de cache edilmez.

## Tahsilat ve fatura ilişkisi

- Tahsilatın belirli faturaya bağlanması zorunlu değildir.
- Fatura ekranından "Tahsilat" başlatılırsa kaynak belge ilişkisi bilgi amaçlı `document_relations` üzerinden tutulabilir.
- Bu ilişki bakiyenin hesabını değiştirmez.
- Kalıcı invoice settlement dağıtım tablosu oluşturulmaz.

## Yaşlandırma

Yaşlandırma raporlamada sanal FIFO uygular:

1. Önce `reversal_of_id` ile birbirine bağlı **tam ters hareket çiftleri** normalize edilir: orijinal hareket ve onu birebir tersleyen hareket aging setinden birlikte çıkarılır. Böylece terslenmiş fatura credit'i başka eski faturayı FIFO ile kapatmış görünmez; terslenmiş tahsilat da yeni borç satırı gibi yaşlandırılmaz.
2. Kalan borç doğuran hareketler `COALESCE(due_date, transaction_date)`, ardından `transaction_date`, ardından `id` sırasına dizilir.
3. Kalan cari azaltıcı kesinleşmiş credit hareketler en eski borçtan başlayarak sanal mahsup edilir.
4. Sonuç DB'ye fatura eşleştirmesi olarak yazılmaz.
5. Satır rengi: yeşil=tam kapanmış, sarı=kısmi, kırmızı=hiç kapanmamış.

Dilimler: vadesi gelmemiş, 1–30, 31–60, 61–90, 91–120, 120+.

## Ters kayıt

Kesinleşmiş cari hareket silinmez. Hata/karşılıksız kıymet gibi durumda yeni ters hareket yazılır ve `reversal_of_id` ile kaynağa bağlanır.

## CHECK kısıtları

- `amount > 0`
- `direction in ('debit','credit')`
