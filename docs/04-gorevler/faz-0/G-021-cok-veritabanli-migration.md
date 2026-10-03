# G-021 — migrate:periods (çok veritabanlı dağıtım)

## Amaç
Dönem tablosuna eklenen bir kolon **tüm dönem veritabanlarında**
çalışmalıdır. 2 şirket × 3 yıl = 6 veritabanı; beş yılda 10+ olur.

Birini atlarsan o dönem açıldığında "column does not exist" hatası
alınır ve bu ancak kullanıcı o döneme geçince fark edilir.

**Bu, master/dönem mimarisinin doğurduğu en büyük işletim sorunudur.**

## Önkoşul
G-002, G-003


## Dokunulacak dosyalar
- `app/Console/Commands/MigratePeriodsCommand.php`
- Master `periods.schema_version` migration/model güncellemesi
- migration status/report DTO/helper'ları
- `tests/Feature/Period/MigratePeriodsCommandTest.php`
- `docs/isletim/migration-dagitim.md`


## Şema / Kod

Bu görev yeni tablo gerektirmiyorsa migration ekleme. Mevcut kod örnekleri ve aşağıdaki mimari sözleşme normatiftir.

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

## Production deploy sınırı

Bu görev **yalnız** `migrate:periods` komutu ve schema-version dağıtım sözleşmesini uygular.

Eski in-place `git pull / down / up` deployment akışı bu görevin kanonik çıktısı değildir. Production deployment Faz 11 **G-1103 / K-239…K-241 immutable release** akışıdır. G-021 yalnız G-1103'ün çağıracağı güvenilir period migration komutunu sağlar.

**Dağıtım öncesi tüm veritabanlarının yedeği alınır** (master dahil).
Geri alma `down()` metoduna değil, yedeğe dayanır.


**Faz bağlamı:** Faz 0 — altyapı.

### Göreve özel kararlar
- Master migration ve migrate:periods ayrıdır.
- Aktif/kapalı/restored period DB'ler schema version uyumuyla güncellenir.
- Restore period önce migrate edilir sonra closed açılır.


### Uygulama ayrıntıları
- Master migration'ları normal Master connection'da; işletme migration'ları `database/migrations/period` altında tutulur.
- `migrate:periods` Master `periods` kayıtlarını dolaşarak her fiziksel period DB'yi aynı schema seviyesine getirir.
- Tek period hatası bütün komut sonucunda açıkça raporlanır; sessiz skip yapılmaz.
- Restore edilmiş kapalı/arşiv period DB de uygulama tarafından açılmadan önce bu migration zincirinden geçirilir.

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
> destekle. Production deploy scripti yazma; immutable release akışı G-1103'ün sorumluluğudur.
