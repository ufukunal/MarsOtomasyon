# Backup, restore ve arşiv

## Recovery set

Tek recovery_set_id altında:
- Master DB
- tüm active/closed period DB
- attachments/files
- manifest/checksum
- gerekli encryption-key recovery prosedürü metadata'sı

izlenir.

## Zamanlama

- günlük otomatik backup
- deploy öncesi
- dönem devri öncesi
- manuel on-demand

En az bir kopya VDS dışındadır.

## Verification

Dosya oluşması tek başına başarı değildir.

Düzenli restore provası:
1. temporary DB/storage alanına restore,
2. checksum,
3. migrate gerekiyorsa uygula,
4. app boot/smoke,
5. integrity,
6. sonucu verified olarak işaretle.

## Restore

Production üzerine doğrudan kör restore yok.

Önce temporary doğrulama.

Archive period:
- restore,
- migrate:periods,
- closed/read-only bağla.

Detached period raporda sessizce atlanmaz.
