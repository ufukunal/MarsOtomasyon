# Yedekleme ve geri yükleme

Faz 0 yedekleme temeli Master veritabanını, Master'da kayıtlı tüm `active` ve
`closed` period veritabanlarını ve `storage/app/attachments` içeriğini kapsar.

`backup:run` başlamadan hemen önce `PrepareBackupSources` period listesini Master
DB'den okur ve period bağlantılarını dinamik olarak backup kaynağına ekler. Arşivlenmiş /
detached period DB'ler Faz 11 restore/archive runbook'u kapsamında ayrıca ele alınır.

## Hedefler

- `backups`: VDS üzerindeki yerel disk.
- `backup_external`: S3 uyumlu harici depolama.

İki hedef de başarıyla yazılmadan backup başarılı kabul edilmez.

## Zamanlama

- 01:00 `backup:clean`
- 01:30 `backup:run`
- 02:00 `backup:monitor`

Saklama temeli: 7 günlük, 4 haftalık, 6 aylık kopya.

## Geri yükleme

1. İstenen recovery setin Master DB, period DB dump'ları ve attachment dosyalarının
   birlikte mevcut olduğunu doğrula.
2. Production bağlantısını değiştirmeden ayrı geçici/staging DB'lere geri yükle.
3. Master verisini ve period kaydını doğrula.
4. Restore edilen period için önce `migrate:periods --company=<id> --year=<yıl>`
   çalıştır.
5. Integrity/smoke doğrulaması tamamlanmadan period'u production'a bağlama.
6. Arşiv dönem restore edildiyse doğrulama sonrası `closed`/read-only aç.

## Restore provası

En az ayda bir gerçek geri yükleme provası yapılır. Yalnız arşiv dosyasının oluşması
yedek doğrulaması sayılmaz. Prova sonucu, kullanılan recovery set kimliği ve doğrulanan
DB/dosya kapsamı operasyon kaydında tutulur.

Faz 11 G-1104/G-1105 bu temeli recovery-set manifest/checksum, off-VDS doğrulama ve
production DR akışıyla genişletir; ikinci bir paralel backup sistemi kurulmaz.
