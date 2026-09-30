# Teknoloji kararları

Kilitlenmiş kararlardır. Karar günlüğüne işlenmeden değiştirilmez.

## Yığın

| Katman | Seçim | Gerekçe |
|---|---|---|
| Dil | PHP 8.3+ | — |
| Çerçeve | Laravel 13 | — |
| Arayüz | **Livewire 3 + kendi bileşenlerimiz** | Filament'in görsel dili istenmedi |
| CSS | **Tek tema dosyası, düz CSS, değişkenlerle** | Tailwind yok, derleme yok |
| JS | **Asgari** | Yalnız barkod odağı, sürükle-bırak, yazdırma köprüsü |
| Veritabanı | PostgreSQL | VDS üzerinde |
| Önbellek / kuyruk / oturum | Valkey | Redis sürücüsü, `REDIS_*` ayarları |
| Kuyruk işçisi | systemd servisi | Gerçek işçi |
| PDF | Browsershot (Chrome) | — |
| Yetki | spatie/laravel-permission, **teams = şirket** | — |
| İşlem geçmişi | spatie/laravel-activitylog | — |
| Yedekleme | spatie/laravel-backup | Harici hedefe |
| Test | Pest | — |
| Kalite | Pint + Larastan | — |

Sunucu: Ubuntu LTS, Nginx, PHP-FPM, PostgreSQL, Valkey.
VDS 6 CPU / 8 GB RAM / 55 GB SSD. cPanel/Plesk kurulmaz.

## Değişmez kurallar

1. **Tek CSS dosyası.** Ekran bazında stil açılmaz; eksik olan temaya eklenir.
2. **JS dosyası eklemek istisnadır.** Varsayılan çözüm Livewire.
3. **Tutar `decimal(18,4)`, miktar `decimal(18,3)`, oran `decimal(7,4)`,
   kur `decimal(18,6)`.** Float kullanılmaz.
4. **Her iş tablosu `company_id` taşır** ve global scope ile filtrelenir.
5. Arayüz Türkçe, tarih `d.m.Y`, sayı `1.234,56`.
6. Dosya yükleme sıkıştırması **yoktur** — kullanıcı elle yapar.
