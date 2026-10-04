# Arşiv dönem ve geri yükleme

## Neden arşiv

Her şirket-yıl bir veritabanıdır. Beş yıl sonra 10+ veritabanı olur ve
55 GB disk dolar. Eski dönemler arşivlenir: veritabanı dışa aktarılıp
sunucudan kaldırılır, `periods.status = archived` yapılır.

Arşiv dönem **seçilemez**; raporu alınamaz. Gerekirse geri yüklenir.

## Arşivleme

```bash
# 1. Yedeğini al (harici hedefe de)
pg_dump -Fc ABCHolding_2023 > /yedek/ABCHolding_2023.dump

# 2. Doğrula — bozuk yedek yedek değildir
pg_restore --list /yedek/ABCHolding_2023.dump > /dev/null

# 3. Veritabanını kaldır
psql -c 'DROP DATABASE "ABCHolding_2023"'

# 4. periods kaydını güncelle
php artisan period:archive --company=1 --year=2023
```

`period:archive` komutu: durum `archived`, `archived_at`, arşiv dosya
yolu ve boyutu `periods` tablosuna yazılır. **Kayıt silinmez** — hangi
dönemin var olduğu bilinmeli.

## Geri yükleme

```bash
# 1. Veritabanını oluştur ve geri yükle
psql -c 'CREATE DATABASE "ABCHolding_2023"'
pg_restore -d ABCHolding_2023 /yedek/ABCHolding_2023.dump

# 2. EKSİK MIGRATION'LARI ÇALIŞTIR  ← atlanırsa hata verir
php artisan migrate:periods --company=1 --year=2023

# 3. Durumu güncelle
php artisan period:restore --company=1 --year=2023
```

**2. adım kritik.** Arşivlendikten sonra çıkan tüm şema değişiklikleri
o veritabanına uygulanmamıştır. `periods.schema_version` ile mevcut
sürüm karşılaştırılır; eksikse geri yükleme komutu uyarır ve migration
çalıştırılmadan dönemi `closed` yapmaz.

## Geri yüklenen dönemin durumu

`closed` olur, `active` değil. Arşivden dönen bir yıla yeni kayıt
girilmez; yalnız rapor alınır. Gerçekten kayıt gerekiyorsa Yönetici
bilinçli olarak açar ve bu `activity_log`'a düşer.

## Ne zaman arşivlenir

Öneri: devir yapılmış ve üzerinden **iki tam yıl geçmiş** dönemler.
Ticari kayıt saklama yükümlülüğü sürdüğü için yedek **silinmez**,
yalnız sunucudan kaldırılır.

## Çok dönemli raporda arşiv

Arşiv dönem seçilirse rapor uyarı verir: *"2023 dönemi arşivde,
rapora dahil edilemedi. Geri yüklemek için Ayarlar › Dönemler."*
Sessizce atlanmaz — eksik veriyle rapor almak yanlış karar demektir.

## Disk takibi

Ana sayfada disk kullanımı gösterilir. %85'i aşınca uyarı:
*"Disk %87 dolu. En eski dönemleri arşivlemeyi değerlendirin."*
