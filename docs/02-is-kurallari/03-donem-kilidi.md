# Dönem kilidi

## Kural

Kapalı döneme kayıt girilemez ve o dönemdeki belgeler değiştirilemez.

Kontrol edilen işlemler: belge kesinleştirme, belge iptali, stok hareketi
oluşturma, cari hareketi yazma, sayım onaylama.

## Uygulama

```php
final class EnsurePeriodOpen
{
    public function handle(Carbon $date): void
    {
        $period = PostingPeriod::query()
            ->where('company_id', CompanyContext::id())
            ->where('year', $date->year)
            ->where('month', $date->month)
            ->first();

        if ($period && $period->status === 'closed') {
            throw new PeriodClosedException(
                sprintf('%02d.%d dönemi kapalı. Bu tarihe kayıt girilemez.',
                    $date->month, $date->year)
            );
        }
    }
}
```

**Satırı olmayan ay açık sayılır.** Dönem kaydı yalnızca kapatılınca oluşur.

## Yeniden açma

- Yalnızca `Yönetici` rolü
- Gerekçe zorunlu
- `audit_log`'a düşer
- Açık bırakılan dönem ana sayfada uyarı olarak gösterilir
