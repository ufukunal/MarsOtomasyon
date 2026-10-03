# G-1106 — Health, monitoring ve alarm

## Amaç
App/DB/Valkey/queue/scheduler/disk/backup/integrity sağlığını tek operational health sisteminde izlemek.

## Önkoşul
G-1102, G-1104.

## Dokunulacak dosyalar
- operational heartbeats
- health_check_runs
- health command/controller
- system health UI
- alert integration
- tests

## Şema / Kod
Model 44 + iş kuralı 60.

## Kurallar
- queue lag/failed jobs.
- scheduler heartbeat.
- backup freshness.
- disk threshold.
- secret log yok.
- business data auto repair yok.

## Kabul ölçütü
- her check ayrı sonuç.
- overall status türetiliyor.
- stale scheduler/backup yakalanıyor.
- failed integrity alarm veriyor.

## İstem
> K-246/K-247/K-255 operational health ve alarm sistemini uygula.
