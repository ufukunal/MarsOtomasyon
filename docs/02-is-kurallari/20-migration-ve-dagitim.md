# Migration ve dağıtım — çok veritabanlı

**Bu, master/dönem mimarisinin doğurduğu en büyük işletim sorunudur ve
ilk sürüm güncellemesinde karşına çıkar.**

## Sorun

Dönem tablosuna bir kolon eklediğinde, bu **tüm dönem veritabanlarında**
çalışmalıdır. 2 şirket × 3 yıl = 6 veritabanı. Beş yıl sonra 10+ olur.

`php artisan migrate` yalnızca bağlı olduğun veritabanında çalışır.
Birini atlarsan o dönem açıldığında "column does not exist" hatası alırsın
ve bunu ancak kullanıcı o döneme geçince fark edersin.

## Çözüm: migrate:periods

```bash
php artisan migrate:periods              # tüm aktif + kapalı dönemler
php artisan migrate:periods --year=2026  # yalnızca 2026
php artisan migrate:periods --company=1
php artisan migrate:periods --status     # hangi dönem hangi migration'da
php artisan migrate:periods --pretend    # çalıştırmadan göster
```

```php
final class MigratePeriodsCommand extends Command
{
    public function handle(): int
    {
        $periods = Period::on('master')
            ->whereIn('status', ['active','closed'])   // arşiv hariç
            ->get();

        $failed = [];

        foreach ($periods as $period) {
            $this->info("→ {$period->database_name}");
            try {
                PeriodContext::use($period->company_id, $period->year);
                Artisan::call('migrate', [
                    '--database' => 'period',
                    '--path'     => 'database/migrations/period',
                    '--force'    => true,
                ]);
            } catch (\Throwable $e) {
                $failed[] = [$period->database_name, $e->getMessage()];
            }
        }

        // rapor: hangileri başarılı, hangileri hatalı
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
```

**Kural:** dağıtım betiği `migrate` değil **`migrate:periods`** çalıştırır.
Biri hata verirse dağıtım durur, kısmen güncellenmiş sistem bırakılmaz.

## Arşiv dönemler

Arşivlenmiş veritabanları migration almaz. Geri yüklendiğinde önce
`migrate:periods --company=X --year=Y` çalıştırılır, sonra açılır.
`periods` tablosuna `schema_version` kolonu eklenir; geri yüklemede
eksik migration varsa uyarı verilir.

## Migration yazma disiplini

**Dönem migration'ı geri alınabilir olmalı** (`down()` yazılır).
Altı veritabanında yarım kalan bir değişikliği geri almak gerekebilir.

**Veri taşıyan migration yazma.** Kolon ekle, veriyi ayrı bir komutla
doldur. Migration altı veritabanında çalışacağı için uzun süren veri
işlemleri dağıtımı kilitler.

**Yeni dönem veritabanı hep güncel şemayla oluşur** — `CreatePeriod`
tüm migration'ları çalıştırır, sorun yok.

## Dağıtım sırası

```
1. Bakım moduna al          php artisan down --secret=...
2. Kodu çek                 git pull
3. Bağımlılıklar            composer install --no-dev -o
4. Master migration         php artisan migrate --database=master --path=database/migrations/master --force
5. TÜM DÖNEMLER             php artisan migrate:periods --force
6. Önbellek                 php artisan optimize:clear && php artisan optimize
7. Yetki önbelleği          php artisan permission:cache-reset
8. Kuyruk işçisini yeniden başlat   php artisan queue:restart
9. Bakım modundan çık       php artisan up
```

**5. adım hata verirse 9'a geçilmez.** Betik durur, kullanıcıya hangi
veritabanının hatalı olduğu bildirilir.

## Geri alma planı

Dağıtım öncesi **tüm veritabanlarının yedeği alınır** (master dahil).
Geri alma: kodu eski sürüme döndür, yedekleri geri yükle. Migration'ın
`down()` metoduna güvenilmez — yedek daha güvenli.

## Sürüm izleme

Master'da `app_version` ayarı tutulur. Ana sayfada gösterilir.
`periods.schema_version` ile karşılaştırılır; uyuşmazlık varsa yönetici
uyarılır: "3 dönem güncellenmemiş".
