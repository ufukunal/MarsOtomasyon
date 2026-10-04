# G-007 — Dönem kilidi (ay bazlı)

**Veritabanı: DÖNEM.** Bu, yıl bazlı dönem veritabanından farklıdır:
burada **yıl içindeki ay kilitleri** tutulur.

## Amaç
Kapalı aya kayıt girilmesini engellemek.

## Önkoşul
G-003, G-005


## Dokunulacak dosyalar
- `app/Actions/Periods/EnsurePeriodOpen.php`
- `app/Exceptions/PeriodClosedException.php`

## Şema / Kod
```php
Schema::connection('period')->create('posting_periods', function (Blueprint $table) {
    $table->id();

    $table->unsignedSmallInteger('year');
    $table->unsignedTinyInteger('month');
    $table->string('status', 10)->default('open');
    $table->unsignedBigInteger('closed_by')->nullable();
    $table->string('closed_by_name')->nullable();
    $table->timestamp('closed_at')->nullable();
    $table->unsignedBigInteger('reopened_by')->nullable();
    $table->string('reopened_by_name')->nullable();
    $table->timestamp('reopened_at')->nullable();
    $table->text('reopen_reason')->nullable();
    $table->timestamps();
    $table->unique(['year','month'], 'posting_periods_unique');
});
```

## Action

`app/Actions/Periods/EnsurePeriodOpen.php`

```php
final class EnsurePeriodOpen
{
    // İlk kontrol: $date->year === PeriodContext::year(); aksi PeriodYearMismatchException
    public function handle(Carbon $date): void
    {
        $period = PostingPeriod::query()
            ->where('year', $date->year)
            ->where('month', $date->month)
            ->first();

        if ($period && $period->status === 'closed') {
            throw new PeriodClosedException(sprintf(
                '%02d.%d dönemi kapalı. Bu tarihe kayıt girilemez.',
                $date->month, $date->year
            ));
        }
    }
}
```

`app/Exceptions/PeriodClosedException.php` — `\RuntimeException` türevi.

## Kurallar
- **Satırı olmayan ay açıktır.** Kayıt yalnızca kapatınca oluşur.
- Yeniden açma: yalnızca `Yönetici`, gerekçe zorunlu, `audit_log`'a düşer
- Açık bırakılmış geçmiş dönem ana sayfada uyarı olarak gösterilir


**Faz bağlamı:** Faz 0 — altyapı; sonraki fazların sözleşmesini bozmamalı.

### Bu göreve özel kanonik notlar
- Kontrol document_date üzerindendir.
- `document_date.year` aktif `PeriodContext::year()` ile eşleşmiyorsa aylık kilit sorgusundan önce işlem reddedilir.
- Yeniden açma role sabit değil ayrı permission + zorunlu gerekçedir.
- Period actor alanlarında Master users FK'si kurma; id+name snapshot kullan.


### Uygulama ayrıntıları
- `posting_periods` period DB'dedir ve `year + month` benzersizdir.
- Kontrol tarihi `document_date`tir; `created_at` dönem kilidi için kullanılmaz.
- Yeniden açma role sabit değildir; ayrı izin ve zorunlu gerekçe ister.
- `closed_by/reopened_by` Master user scalar ID + isim snapshot'tır; cross-DB FK kurulmaz.

## Kabul ölçütü
- Açık dönem: istisna fırlatmaz
- Kapalı dönem: `PeriodClosedException` fırlatır
- Satırı olmayan ay: istisna fırlatmaz
- Yeniden-açma izni olmayan kullanıcı kapalı ayı yeniden açamaz


## İstem
> posting_periods tablosu için migration, PostingPeriod modeli,
> EnsurePeriodOpen action'ı, PeriodClosedException ve PeriodYearMismatchException sınıflarını yaz.
> Model `PeriodModel`'den türesin; BelongsToCompany/global scope kullanmasın. Satırı olmayan ay açık sayılsın.
