# G-1105 — Restore, arşiv ve disaster recovery

## Amaç
Backup'ı temporary ortamda doğrulamak, archive period'u güvenli read-only bağlamak ve production recovery runbook'unu uygulamak.

## Önkoşul
G-1104.

## Dokunulacak dosyalar
- restore_runs migration/model
- restore command/action
- archive attach flow
- backup/restore UI
- restore tests

## Şema / Kod
İş kuralı 59.

## Kurallar
- production'a kör restore yok.
- temporary verify önce.
- archived restore → migrate:periods → closed/read-only.
- detached archive açık hata.

## Kabul ölçütü
- restore prova DB'si açılıyor.
- checksum/migrate/integrity geçiyor.
- verified_at yazılıyor.
- archive read-only.
- production recovery açık runbook ile.

## İstem
> K-244/K-245/K-252 restore ve archive recovery akışını uygula.
