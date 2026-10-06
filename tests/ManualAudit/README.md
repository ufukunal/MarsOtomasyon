# ManualAudit test paketi

Bu klasör kod bütünlüğü, kalite ve güvenlik denetimi için hazırlanan 273 planlı kontrolü içerir.

## Kapsam

- Architecture ve kod bütünlüğü
- Master / Period fiziksel izolasyonu
- Transaction atomicity
- Concurrency ve race-condition guardları
- Idempotency
- Optimistic locking
- Migration / model parity
- Numeric ve finansal precision
- Static security guardları
- Authorization / IDOR
- HTTP, signed URL ve download güvenliği
- Template/XSS güvenliği
- Mass assignment
- Marketplace channel güvenliği
- Upload/import ve spreadsheet injection güvenliği
- Log/secret redaction
- PHP/Laravel kalite
- Performance guardları
- Dead/duplicate code guardları
- Production configuration güvenliği
- Bilinen hata regresyonları

CoverageManifestTest.php kategori önekleriyle tanımlanmış 001..273 kontrol kimliğinin eksiksiz olduğunu doğrular.

Bu paket oluşturulurken testler çalıştırılmamıştır. Çalıştırma ayrı final test aşamasında yapılmalıdır.
