# Eşzamanlılık: çift gönderim, iyimser kilit, kuyruk

## 1. Çift gönderim koruması (idempotency)

**Sorun:** kullanıcı "Kesinleştir"e iki kez basar, ya da ağ kopar ve
tarayıcı isteği tekrarlar. İki fatura oluşur, stok iki kez düşer.

**Çözüm:** durum değiştiren her istek bir **istek anahtarı** taşır.

```php
// idempotency_keys (DÖNEM veritabanı)
Schema::connection('period')->create('idempotency_keys', function (Blueprint $table) {
    $table->id();
    $table->string('key', 64)->unique();          // istemcinin ürettiği UUID
    $table->foreignId('user_id');
    $table->string('action', 60);                 // post_document, carry_period...
    $table->string('result_type', 40)->nullable();
    $table->unsignedBigInteger('result_id')->nullable();
    $table->string('status', 12);                 // processing | done | failed
    $table->timestamp('created_at');
});
```

Akış:

```
1. Form açılırken istemci bir UUID üretir, gizli alanda tutar
2. Gönderimde anahtar sunucuya gelir
3. Sunucu: anahtar var mı?
   - yok  → 'processing' kaydı açılır, iş yapılır, 'done' + sonuç yazılır
   - 'processing' → "işlem sürüyor" hatası (çift tıklama)
   - 'done' → iş TEKRARLANMAZ, önceki sonuç döndürülür
4. Kayıtlar 7 gün sonra temizlenir
```

**Hangi işlemlerde zorunlu:** belge kesinleştirme, belge iptali, tahsilat,
ödeme, transfer gönderme/teslim alma, sayım kesinleştirme, dönem devri,
içe aktarma başlatma.

## 2. İyimser kilit (optimistic locking)

**Sorun:** iki kullanıcı aynı taslak belgeyi açar, ikisi de kaydeder.
İkincisi birincinin değişikliğini **sessizce ezer**.

`lockForUpdate` bunu çözmez — o yalnızca kesinleştirme anını korur,
kullanıcının formu açık tuttuğu 10 dakikayı değil.

**Çözüm:** her düzenlenebilir tabloda `version` kolonu.

```php
$table->unsignedInteger('version')->default(1);
```

```php
$affected = Document::where('id', $id)
    ->where('version', $expectedVersion)
    ->update([...$data, 'version' => $expectedVersion + 1]);

if ($affected === 0) {
    throw new StaleRecordException(
        'Bu kayıt siz düzenlerken başka bir kullanıcı tarafından değiştirildi. '
        .'Sayfayı yenileyip değişikliklerinizi tekrar uygulayın.'
    );
}
```

Form `version` değerini gizli alanda taşır. Çakışmada kullanıcıya
**hangi alanların değiştiği** gösterilir, kör "yenile" denmez.

**Nerede zorunlu:** belgeler, cari ve ürün kartları, fiyat listeleri,
reçeteler, ayarlar.

## 3. Kuyruk hata yönetimi

```php
class SyncChannelStock implements ShouldQueue
{
    public int $tries = 3;
    public int $backoff = 30;              // 30 sn, sonra 60, sonra 120
    public int $timeout = 120;
    public bool $failOnTimeout = true;

    public function __construct(
        public int $companyId,
        public int $year,
        public string $idempotencyKey,     // yeniden denemede iş tekrarlanmasın
    ) {}

    public function handle(): void
    {
        PeriodContext::use($this->companyId, $this->year);
        // ...
    }

    public function failed(\Throwable $e): void
    {
        PeriodContext::use($this->companyId, $this->year);
        activity()->withProperties(['error' => $e->getMessage()])
            ->log('Kuyruk işi başarısız: '.static::class);
        // yöneticiye bildirim
    }
}
```

**Kurallar:**

- Her iş `company_id` ve `year` taşır, `handle()` başında bağlam kurar
- Yan etkisi olan iş **idempotent** olmalı — yeniden denemede stok iki
  kez düşmemeli. İstek anahtarı bunu sağlar.
- `failed()` metodu **zorunlu**: hatayı loglar, yöneticiye bildirir
- `failed_jobs` tablosu izlenir; gecelik kontrol boş değilse uyarı verir
- Uzun işler (içe aktarma, devir) ilerleme yazar, kullanıcı görebilir

## 4. Deadlock

Eşzamanlı stok hareketlerinde PostgreSQL kilitlenmesi olabilir.

```php
DB::transaction(function () { ... }, attempts: 3);
```

Kilit sırası **her zaman aynı** olmalı: önce `stock_balances` (ürün id
sırasına göre), sonra `documents`. Farklı sıra iki işlemin birbirini
beklemesine yol açar.

```php
$productIds = collect($lines)->pluck('product_id')->sort()->values();  // SIRALA
```
