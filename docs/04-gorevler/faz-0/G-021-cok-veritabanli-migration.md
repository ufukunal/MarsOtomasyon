# G-021 — migrate:periods (çok veritabanlı dağıtım)

## Amaç
Dönem tablosuna eklenen bir kolon **tüm dönem veritabanlarında**
çalışmalıdır. 2 şirket × 3 yıl = 6 veritabanı; beş yılda 10+ olur.

Birini atlarsan o dönem açıldığında "column does not exist" hatası
alınır ve bu ancak kullanıcı o döneme geçince fark edilir.

**Bu, master/dönem mimarisinin doğurduğu en büyük işletim sorunudur.**

## Önkoşul
G-002, G-003

## Komut

```bash
php artisan migrate:periods              # tüm aktif + kapalı
php artisan migrate:periods --year=2026
php artisan migrate:periods --company=1
php artisan migrate:periods --status     # hangi dönem hangi migration'da
php artisan migrate:periods --pretend
```

Kod: `docs/02-is-kurallari/20-migration-ve-dagitim.md` içindeki
`MigratePeriodsCommand` sınıfını birebir uygula.

## Kurallar

- Arşiv dönemler **atlanır**; geri yüklendiğinde ayrıca çalıştırılır
- Bir dönem hata verirse komut devam eder ama **sonuçta hata döner**;
  dağıtım betiği durur
- Sonunda rapor: kaç dönem güncellendi, hangileri hatalı
- `periods.schema_version` güncellenir
- Ana sayfada uyum kontrolü: "3 dönem güncellenmemiş" uyarısı

## Migration yazma disiplini

- Dönem migration'ı **geri alınabilir** olmalı (`down()` yazılır)
- **Veri taşıyan migration yazılmaz** — kolon eklenir, veri ayrı
  komutla doldurulur. Uzun süren işlem altı veritabanında dağıtımı kilitler
- Yeni dönem veritabanı hep güncel şemayla oluşur (`CreatePeriod`)

## Dağıtım betiği

```
1. down --secret=...
2. git pull
3. composer install --no-dev -o
4. migrate --database=master --path=database/migrations/master --force
5. migrate:periods --force          ← HATA VERİRSE DUR
6. optimize:clear && optimize
7. permission:cache-reset
8. queue:restart
9. up
```

**Dağıtım öncesi tüm veritabanlarının yedeği alınır** (master dahil).
Geri alma `down()` metoduna değil, yedeğe dayanır.

## Kabul ölçütü
- 4 dönem veritabanı varken komut dördünü de güncelliyor
- `--status` hangi dönemin hangi migration'da olduğunu gösteriyor
- Bir dönem hata verince komut hata koduyla çıkıyor
- Arşiv dönem atlanıyor
- Yeni oluşturulan dönem zaten güncel şemada

## İstem
> MigratePeriodsCommand'ı yaz. Tüm aktif ve kapalı dönemleri dolaşsın,
> arşivleri atlasın, her biri için PeriodContext::use çağırıp migrate
> çalıştırsın. Hatalı olanları toplayıp sonunda raporlasın ve hata
> koduyla çıksın. --year, --company, --status, --pretend seçeneklerini
> destekle. Dağıtım betiğini docs/isletim/ altına yaz.
