# Teknoloji kararları

| Katman | Karar |
|---|---|
| Dil / framework | PHP 8.3+ / Laravel 13 |
| UI | Livewire 3 + kendi bileşenlerimiz |
| CSS | Tek düz CSS tema; Tailwind yok |
| JS | Asgari |
| DB | PostgreSQL |
| Cache/session/queue | Valkey, ayrı DB numaraları |
| PDF | Browsershot |
| Yetki | spatie/laravel-permission |
| Audit | spatie/laravel-activitylog |
| Backup | spatie/laravel-backup |
| Test | Pest, gerçek PostgreSQL |
| Kalite | Pint + Larastan |
| Sunucu | Ubuntu LTS VDS 6 CPU / 8 GB / 55 GB |

Filament, DevExpress ve Stimulsoft reddedildi; tekrar önerilmez.

## Değişmez teknik kurallar

1. Tutar `decimal(18,4)`, miktar `decimal(18,3)`, oran `decimal(7,4)`, kur `decimal(18,6)`; PHP float yok.
2. Para aritmetiği string + BCMath tabanlı `Money` ile yapılır.
3. İş kuralı Livewire bileşenine değil Action/Domain katmanına yazılır.
4. Master ve period bağlantıları ayrıdır.
5. Period tablolarında `company_id`, `BelongsToCompany` ve şirket global scope'u yoktur; izolasyon fiziksel DB'dir.
6. Master modelleri de şirket global scope'u kullanmaz; erişim Master yetki tablolarıyla kontrol edilir.
7. Durum değiştiren istek idempotency key taşır.
8. Düzenlenebilir kayıt `version` ile optimistic lock kullanır.
9. Kritik kurallar DB CHECK constraint ile de korunur.
10. Test SQLite değil gerçek PostgreSQL üzerinde çalışır.
