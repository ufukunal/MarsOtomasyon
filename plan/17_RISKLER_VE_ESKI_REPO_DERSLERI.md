# 17 — Riskler ve Eski Repo Dersleri

## R1 — Aynı işi birden fazla motorla yapmak
En büyük risk stok, cari veya belge state'ini birden fazla yerde authority yapmaktır.

Çözüm:
- cari: `account_transactions`
- stok: `stock_movements`
- state transition: domain use-case
- external side effect: outbox

## R2 — UI prototipini gerçek davranış sanmak
Prototype localStorage/demo renderer kodu production domain değildir. V16.3 görsel/akış sözleşmesi olarak kullanılır; business data ve action Laravel backend'den gelir.

## R3 — Dead button / generic renderer
Eski yaklaşımda ekran sayısını artırmak uğruna işlevsiz buton veya aynı generic formun farklı başlıkla gösterilmesi kabul edilmez.

## R4 — Transaction parçalanması
Fatura post edildi ama stok/cari güncellenmedi gibi yarım durumlar yasaktır. Cross-domain business action tek transaction + outbox kullanır.

## R5 — Duplicate entegrasyon
Webhook/polling/retry aynı siparişi veya faturayı iki kez yaratabilir. External id + idempotency DB constraint ile korunur.

## R6 — SQLite test yanılsaması
Production PostgreSQL ise CI de PostgreSQL olmalıdır. DB constraint, locking, numeric ve query davranışı gerçek motorla test edilir.

## R7 — Aşırı mimari
Az kullanıcı/tek sunucu hedefinde Kubernetes, microservice, ayrı search cluster, generic workflow/QMS/report platformu geliştirmeyi yavaşlatır. Gerçek ihtiyaç oluşmadan kurulmaz.

## R8 — Master data değişince geçmiş belge değişmesi
Posted belge gerekli master alanları snapshot tutar; fiyat/KDV/kur/unvan geçmişi son master değerden yeniden hesaplanmaz.

## R9 — Stok iki kez düşmesi
Sipariş→sevk→fatura zincirinde fiziksel hareket authority açık olmalıdır. Aynı fiziksel çıkış dispatch ve invoice tarafından iki kez üretilemez.

## R10 — Backup var, restore yok
Restore edilmeyen backup güvence değildir. Periyodik restore drill zorunludur.

## R11 — Yetki yalnız UI'da
Gizlenen buton güvenlik değildir. Policy/action seviyesinde server-side authorization şarttır.

## R12 — Plan drift
Kod ile V16.3 planı çelişirse sessizce legacy davranış korunmaz. Karar kaydı güncellenir veya kod plana döndürülür.

## Eski repo kullanım kuralı
`MarsEski`:
- business edge-case kaynağı olabilir
- migration veri eşleme kaynağı olabilir
- test senaryosu kaynağı olabilir

Ama legacy application code blok halinde yeni projeye taşınmaz.
