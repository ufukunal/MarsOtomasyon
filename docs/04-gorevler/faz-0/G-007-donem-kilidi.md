# G-007 — Dönem kilidi (ay bazlı)

**Veritabanı: DÖNEM.** Bu, yıl bazlı dönem veritabanından farklıdır:
burada **yıl içindeki ay kilitleri** tutulur.

## Amaç
Kapalı aya kayıt girilmesini engellemek.

## Önkoşul
G-003, G-005

## Şema

```php
Schema::create('posting_periods', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained();
    $table->unsignedSmallInteger('year');
    $table->unsignedTinyInteger('month');
    $table->string('status', 10)->default('open');
    $table->foreignId('closed_by')->nullable()->constrained('users');
    $table->timestamp('closed_at')->nullable();
    $table->foreignId('reopened_by')->nullable()->constrained('users');
    $table->timestamp('reopened_at')->nullable();
    $table->text('reopen_reason')->nullable();
    $table->timestamps();
    $table->unique(['company_id','year','month'], 'posting_periods_unique');
});
```

## Action

`app/Actions/Periods/EnsurePeriodOpen.php`

```php
final class EnsurePeriodOpen
{
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

## Kabul ölçütü
- Açık dönem: istisna fırlatmaz
- Kapalı dönem: `PeriodClosedException` fırlatır
- Satırı olmayan ay: istisna fırlatmaz
- Yönetici olmayan kullanıcı dönemi açamaz

## İstem
> posting_periods tablosu için migration, PostingPeriod modeli,
> EnsurePeriodOpen action'ı ve PeriodClosedException sınıfını yaz.
> Model BelongsToCompany trait'ini kullansın. Satırı olmayan ay açık sayılsın.
