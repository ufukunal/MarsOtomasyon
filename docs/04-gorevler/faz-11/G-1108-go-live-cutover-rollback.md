# G-1108 — Go-live cutover ve rollback

## Amaç
Final backup, maintenance, deploy, migration, integrity, smoke ve trafik açma sırasını uygulanabilir runbook haline getirmek.

## Önkoşul
G-1103…G-1107.

## Dokunulacak dosyalar
- go-live runbook
- smoke command/checklist
- rollback runbook
- deployment history UI

## Şema / Kod
İş kuralı 61.

## Kurallar
- final recovery set.
- integrity:all.
- health/smoke.
- trafik ancak green sonrası.
- schema/data riskinde otomatik down yok.

## Kabul ölçütü
- dry-run uygulanabilir.
- failure noktalarında açık stop/rollback yolu.
- release rollback testli.
- restore escalation yolu belgeli.

## İstem
> K-250/K-251/K-254 canlı cutover ve rollback runbook'unu uygula.
