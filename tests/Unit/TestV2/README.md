# Test v2.0 gerçek davranış paketi

Bağımsız test klasörleri:
- Unit/TestV2: Üretim maliyeti/aritmetik, rapor filtreleri, şablon güvenliği, kanal olay imzası, log redaction, yazdırma sürücüleri, Türkçe sayılar.
- Feature/TestV2: İlgili web rotalarında auth boundary, webhook hesap yok senaryoları, anonim domain yazma reddi, kanal stok sınırları.

Testleri yalnız yazmak PASS sayılmaz. GitHub Actions Quality run sonuçlarını ve varsa başarısız senaryoları docs/testing/test-v2-scope.md ile birlikte değerlendirin.

Unit:
  vendor/bin/pest tests/Unit/TestV2 --display-warnings

Feature (yalnız izole test PostgreSQL):
  vendor/bin/pest tests/Feature/TestV2 --display-warnings

CI: .github/workflows/quality.yml R1 adımına entegredir. R2/R3/R4 önceki test koşullarını korur.
