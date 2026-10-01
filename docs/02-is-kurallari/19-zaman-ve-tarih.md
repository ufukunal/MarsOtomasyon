# Zaman ve tarih

## Saat dilimi

```
APP_TIMEZONE=Europe/Istanbul
```

Veritabanında `timestamp` alanları **UTC** saklanır, ekranda yerel saate
çevrilir. `date` alanları (belge tarihi, vade) saat taşımaz, olduğu gibi
saklanır.

## İki farklı tarih — karıştırılmaz

| Alan | Anlam | Kim belirler |
|---|---|---|
| `movement_date`, `document_date` | **İş tarihi** — belgenin ait olduğu gün | Kullanıcı |
| `created_at`, `posted_at` | **Sistem zamanı** — kaydın fiilen yazıldığı an | Sistem |

Kullanıcı 1 Ekim'de, 30 Eylül tarihli fatura kesebilir. O zaman:
`document_date = 30.09`, `created_at = 01.10`.

**Dönem kilidi `document_date`'e bakar**, `created_at`'e değil.
Aksi halde kapalı aya geriye dönük kayıt girilebilirdi.

Raporlar da `document_date` kullanır. Denetim izi `created_at` kullanır.

## İş tarihi sınırları

- Gelecek tarihli belge: **izinli**, ama uyarı verilir
  (sipariş ileri tarihli olabilir)
- Dönem aralığı dışı tarih: **engellenir**
  (`ABCHolding_2026` veritabanına 2025 tarihli belge yazılamaz)
- Kapalı aya kayıt: **engellenir**

```php
final class ValidateDocumentDate
{
    public function handle(Carbon $date): void
    {
        $period = Period::current();

        if ($date->lt($period->starts_on) || $date->gt($period->ends_on)) {
            throw new \DomainException(
                "Belge tarihi {$period->year} dönemi dışında. ".
                "Doğru döneme geçin veya tarihi düzeltin."
            );
        }

        app(EnsurePeriodOpen::class)->handle($date);
    }
}
```

## Gece yarısı sınırı

Sayım, sevkiyat ve gün sonu işlemleri gece yarısını geçebilir. Bu
işlemlerde **iş tarihi kullanıcıdan alınır**, `now()` kullanılmaz.

Örnek: 23:50'de başlayan sayım 00:20'de biterse, sayım tarihi kullanıcının
girdiği gündür — sistem saati değil.

## Vade hesabı

```php
$dueDate = $documentDate->copy()->addDays($contact->term_days ?? $company->default_term_days);
```

Vade `document_date`'ten hesaplanır, `created_at`'ten değil.
Hafta sonu ve tatil kaydırması **yapılmaz** (istenirse sonra eklenir).

## Yaşlandırma raporları

Bugünden geriye değil, **rapor tarihinden** geriye hesaplanır.
Kullanıcı "30.06.2026 itibarıyla yaşlandırma" alabilmeli.

## Kur tarihi

İthalat ve alış belgelerinde kur, **belge tarihinin** kurudur, bugünün
değil. Kur tablosunda o tarih yoksa en yakın önceki tarih kullanılır ve
kullanıcıya hangi tarihin kuru kullanıldığı gösterilir.
