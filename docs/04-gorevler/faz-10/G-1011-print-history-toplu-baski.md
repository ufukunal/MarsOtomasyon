# G-1011 — Print history ve toplu baskı

## Amaç
Tekil/toplu baskı işlerini template/profile/source provenance ile izlemek.

## Önkoşul
G-1010.

## Dokunulacak dosyalar
- print_jobs migration/model
- batch print Action/job
- print history UI
- tests

## Şema / Kod
Kanonik model 43.

## Kurallar
- Profile çözümü K-222.
- Template revision snapshot metadata.
- Hassas belge payload'ı çoğaltılmaz.
- Tekrar baskı current/original revision seçimini audit eder.
- Bu görev `integrity:print-provenance` kontrolünün sahibidir. Kontrol period `document_print_snapshots` içindeki template_key+template_revision_no provenance'ının ilgili şirketin Master template revision'ında çözülebildiğini ve baskıda seçilen revision metadata'sıyla çelişmediğini raporlar; cross-DB FK kurmaz ve otomatik düzeltme yapmaz.

## Kabul ölçütü
- Batch her kaydı izliyor.
- Failure diğer history satırlarını bozmuyor.
- Reprint provenance doğru.
- Profile resolution doğru sıra.
- `integrity:print-provenance` bilerek bozulan template revision provenance'ını mismatch olarak yakalıyor.

## İstem
> K-217/K-223/K-224 print job/history ve toplu baskıyı uygula.
