# G-1112 — Çok dönemli rapor altyapısı

## Amaç
"Son üç yılın satışı" gibi raporlar. PostgreSQL'de veritabanları arası
JOIN **yoktur**; her dönem ayrı sorgulanıp sonuç birleştirilir.

## Önkoşul
G-1110

## Yaklaşım

```php
final class MultiPeriodQuery
{
    public function run(array $periods, Closure $query): Collection
    {
        $results = collect();
        $current = [PeriodContext::companyId(), PeriodContext::year()];

        foreach ($periods as $period) {
            PeriodContext::use($period->company_id, $period->year);
            $results = $results->merge(
                $query()->map(fn ($row) => tap($row, fn ($r) => $r->period_year = $period->year))
            );
        }

        PeriodContext::use(...$current);   // eski bağlamı geri yükle
        return $results;
    }
}
```

## Kurallar
- Sorgu bittiğinde **eski bağlam geri yüklenir** — yoksa kullanıcı
  farkında olmadan başka dönemde çalışmaya devam eder
- Sonuçlara `period_year` eklenir ki hangi yıla ait olduğu bilinsin
- Arşiv dönem seçilirse uyarı: önce geri yüklenmeli
- Yavaş olabilir; ekranda ilerleme gösterilir, uzun raporlar kuyruğa atılır

## Performans notu
Sık kullanılan çok dönemli raporlar için master'da özet tablo tutulabilir
(yıl sonu devrinde doldurulur). Bu, Faz 10'da ihtiyaç görülürse eklenir.

## Kabul ölçütü
- Üç dönemden veri çekilip birleşiyor
- Sonuçta `period_year` doğru
- Sorgu sonrası aktif dönem değişmemiş oluyor
- Arşiv dönem uyarı veriyor

## İstem
> MultiPeriodQuery sınıfını ve çok dönemli rapor seçicisini yaz.
> Sorgu bittiğinde eski bağlamı mutlaka geri yükle. Sonuç satırlarına
> period_year ekle.
