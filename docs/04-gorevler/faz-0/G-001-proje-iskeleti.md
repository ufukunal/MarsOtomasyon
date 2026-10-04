# G-001 — Proje iskeleti

## Amaç
Laravel projesini kurmak, bağımlılıkları eklemek, ortam ayarlarını yapmak.

## Önkoşul
Yok. İlk görev.


## Dokunulacak dosyalar
- `config/permission.php`
- `config/filesystems.php`
- `database/migrations/master/`
- `database/migrations/period/`
- `resources/css/app.css`
- `resources/js/app.js`


## Şema / Kod

Bu görevde aşağıdaki mevcut kod/şema örnekleri normatiftir. Yeni tablo gerekmiyorsa migration ekleme; mevcut mimari sözleşmeyi bozacak ek şema uydurma.

## Adımlar

1. Laravel 13 projesi kur (`composer create-project laravel/laravel .`)
2. Şu paketleri ekle:
   ```bash
   composer require livewire/livewire
   composer require spatie/laravel-permission
   composer require spatie/laravel-activitylog
   composer require spatie/laravel-backup
   composer require spatie/browsershot
   composer require --dev pestphp/pest pestphp/pest-plugin-laravel
   composer require --dev larastan/larastan laravel/pint
   ```
3. Yayınla:
   ```bash
   php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
   php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag=activitylog-migrations
   php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider"
   ```
4. `config/permission.php` içinde `'teams' => true` ve
   `'team_foreign_key' => 'company_id'` yap
5. `.env.example` düzenle:
   ```
   APP_NAME=MarsOtomasyon
   APP_LOCALE=tr
   APP_TIMEZONE=Europe/Istanbul
   DB_CONNECTION=master
   DB_MASTER_DATABASE=MarsProject_Master
   CACHE_STORE=redis
   QUEUE_CONNECTION=redis
   SESSION_DRIVER=redis
   REDIS_HOST=127.0.0.1
   FILESYSTEM_DISK=local
   PRINTING_DRIVER=browser
   ```
6. `config/filesystems.php` içine `attachments` diski ekle:
   ```php
   'attachments' => [
       'driver' => env('ATTACHMENTS_DISK_DRIVER', 'local'),
       'root'   => storage_path('app/attachments'),
       'throw'  => false,
   ],
   ```
7. Migration klasörlerini ayır: `database/migrations/master/` ve
   `database/migrations/period/`
8. `resources/css/app.css` ve `resources/js/app.js` oluştur (şimdilik boş)
8. Pint ve Larastan yapılandır


## Kurallar

**Faz bağlamı:** Faz 0 — altyapı; sonraki fazların sözleşmesini bozmamalı.

### Bu göreve özel kanonik notlar
- Kurulumda period tablosu üretme; yalnız bağlantı/migration dizin iskeletini hazırla.
- `php artisan migrate` tek başına bütün period DB dağıtımı değildir; sonraki G-021 migrate:periods sözleşmesine uyumlu temel hazırla.


### Uygulama ayrıntıları
- Bu görev yalnız Laravel/Laravel 13 proje iskeleti, paketler, temel klasörler ve test altyapısını kurar; business tablo/migration üretmez.
- `master` ve `period` bağlantılarının daha sonra tanımlanacağı config yapısını hazırlar; period DB oluşturmaz.
- Pest, Pint ve Larastan başlangıç konfigürasyonu bu görevde çalışır hale gelir.
- G-021'de kullanılacak master/period migration dizin ayrımına uygun klasör yapısı hazırlanır.

## Kabul ölçütü
```bash
php artisan --version     # Laravel 13.x
php artisan migrate       # hatasız
./vendor/bin/pest         # yeşil
```


## İstem
> Laravel 13 projesi kur. Şu paketleri ekle: livewire/livewire,
> spatie/laravel-permission, spatie/laravel-activitylog, spatie/laravel-backup,
> spatie/browsershot; dev olarak pest, larastan, pint. permission config'inde
> teams=true ve team_foreign_key=company_id yap. .env.example'ı yukarıdaki
> değerlerle düzenle. filesystems.php'ye attachments diski ekle.
> Başka hiçbir şey yapma.
