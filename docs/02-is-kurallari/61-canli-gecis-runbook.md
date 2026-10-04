# Canlı geçiş runbook

## Go-live öncesi

- tüm faz acceptance testleri yeşil
- real PostgreSQL
- Pint/Larastan/Pest
- integrity:all
- authorization/security review
- backup restore prova verified
- production secrets/config hazır
- DNS/TLS hazır
- queue/scheduler/process manager hazır
- disk/storage kapasitesi kontrol
- print/channel external bağlantı smoke testleri

## Cutover

1. maintenance/read-only
2. final recovery set
3. deploy release
4. Master migration
5. migrate:periods
6. integrity:all
7. queue/scheduler start/restart
8. app health
9. smoke test:
   - login/company/period
   - kart read
   - satış/alıs test akışı uygun sandbox/test veriyle
   - stock/contact read
   - report
   - print PDF
   - channel connection test
10. trafik aç

## Rollback

Kod sorunu ve schema compatible:
- previous release symlink
- workers restart
- health

Schema/data sorunu:
- otomatik migration down zorlanmaz,
- forward-fix veya verified recovery set restore runbook'u.

## Canlı sonrası

İlk dönem yakından izlenir:
- errors
- queue
- scheduler
- backup
- disk
- DB health
- integrity
- channel sync failures

Sorunlar audit/correlation id ile izlenir.
