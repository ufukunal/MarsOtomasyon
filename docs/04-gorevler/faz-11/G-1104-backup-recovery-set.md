# G-1104 — Backup recovery set

## Amaç
Master, tüm period DB'ler ve dosyaları tek recovery set altında otomatik/on-demand yedeklemek.

## Önkoşul
G-1102, storage abstraction.

## Dokunulacak dosyalar
- backup_runs migration/model
- backup command/job
- manifest/checksum
- scheduler
- backup tests

## Şema / Kod
Model 44 + iş kuralı 59.

## Kurallar
- günlük.
- deploy/carry öncesi.
- off-VDS copy.
- checksum manifest.
- secret plaintext backup metadata yok.

## Kabul ölçütü
- Master + tüm period DB manifestte.
- files dahil.
- checksum doğrulanıyor.
- failed backup visible.
- stale backup health'i degraded/failed yapıyor.

## İstem
> K-242…K-244 recovery-set backup sistemini uygula.
