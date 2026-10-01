# G-013 — Yedekleme ve kuyruk

## Amaç
Günlük yedek, harici hedefe kopyalama, çalışan kuyruk işçisi.

Bu sistem stok, cari ve kasanın tek kaydıdır. Yedekleme ertelenebilir bir
iş değildir; Faz 0'ın parçasıdır.

## Önkoşul
G-001


## Dokunulacak dosyalar
- `config/backup.php`
- `storage/app/attachments`
- `routes/console.php`


## Şema / Kod

Bu görevde aşağıdaki mevcut kod/şema örnekleri normatiftir. Yeni tablo gerekmiyorsa migration ekleme; mevcut mimari sözleşmeyi bozacak ek şema uydurma.

## Adımlar

1. `config/backup.php` düzenle:
   - Veritabanı + `storage/app/attachments` dizini
   - Hedef: yerel disk **ve** harici disk (S3 uyumlu)
   - Saklama: günlük 7, haftalık 4, aylık 6

2. **Master her yedekte olmalı.** Mastersız dönem veritabanı işe yaramaz —
   kimlik/yetki/dönem meta verisi orada. Yedek betiği tüm dönem veritabanlarını **ve** master'ı alır.

3. Zamanlama (`routes/console.php`):
```php
Schedule::command('backup:clean')->daily()->at('01:00');
Schedule::command('backup:run')->daily()->at('01:30');
Schedule::command('backup:monitor')->daily()->at('02:00');
```

3. Kuyruk işçisi — systemd servisi:
```ini
[Unit]
Description=Mars queue worker
After=network.target

[Service]
User=www-data
Restart=always
ExecStart=/usr/bin/php /var/www/mars/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600

[Install]
WantedBy=multi-user.target
```

4. Cron (tek satır):
```
* * * * * cd /var/www/mars && php artisan schedule:run >> /dev/null 2>&1
```

5. `docs/isletim/yedekleme.md` yaz: geri yükleme adımları ve **aylık
   geri yükleme provası** hatırlatması

## Kritik not
**Denenmemiş yedek yedek değildir.** Ayda bir geri yükleme provası yapılır
ve sonucu not edilir.


## Kurallar

**Faz bağlamı:** Faz 0 — altyapı; sonraki fazların sözleşmesini bozmamalı.

### Bu göreve özel kanonik notlar
- Master ve period DB'leri ayrı yedekle.
- Restore edilen period önce migrate:periods sonra closed açılır.


### Uygulama ayrıntıları
- Master DB ve her period DB ayrı yedeklenebilir olmalıdır.
- Yedek politikası yalnız DB değil attachment/dosya disklerini de kapsar.
- Restore edilen period DB önce `migrate:periods` ile güncellenir, ardından closed/read-only açılır.
- Restore testi gerçek geri yükleme provasıdır; yalnız backup dosyasının oluşmasını test etmek yeterli değildir.

## Kabul ölçütü
```bash
php artisan backup:run      # hatasız, arşiv oluşur
php artisan queue:work --once
```
- Arşiv hem yerel hem harici hedefte
- Yedek içinde veritabanı ve attachments dizini var


## İstem
> config/backup.php dosyasını veritabanı ve storage/app/attachments'i
> yedekleyecek, yerel ve harici iki hedefe yazacak şekilde düzenle.
> routes/console.php'ye günlük zamanlamaları ekle. systemd servis dosyasını
> ve cron satırını docs/isletim/ altına yaz.
