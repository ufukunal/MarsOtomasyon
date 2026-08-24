# 17 — Riskler ve MarsEski Dersleri V4

## R1 — Scope creep / aşırı mimari
Ön muhasebe uygulamasını generic ERP/QMS/PLM/BPM platformuna çevirmek en büyük kapsam riskidir.

Koruma:
- V16.3 UI baseline
- basit üretim
- bakiye bazlı cari
- tek fiyat
- lot/seri core yok
- generic designer/QMS/shop-floor yok
- tek sunucu/az kullanıcı deployment hedefi

## R2 — Legacy code taşıma
`MarsEski` application code blok halinde copy/paste edilmez. Yalnız business rule, edge-case, test ve migration bilgisi alınır.

## R3 — UI prototipini domain sanmak
Prototype/localStorage/demo renderer production truth değildir. V16.3 görsel/akış sözleşmesidir; data ve action Laravel backend'den gelir.

## R4 — Generic renderer / dead button
Ekran sayısını artırmak uğruna generic `Durum/Açıklama/Kaydet` formu veya no-op button kabul edilmez. Screen-specific acceptance test gerekir.

## R5 — Transaction parçalanması
Fatura posted olup stok/cari eksik kalamaz. Kullanıcı açısından tek business action tek transaction + outbox sınırına uyar.

## R6 — Cari modelinin OpenItem'a dönmesi
Geliştirici alışkanlığıyla invoice allocation/paid-partial settlement eklenmesi yasaktır. `account_transactions` tek cari authority'dir.

## R7 — Duplicate stock effect
Dispatch + Invoice veya GoodsReceipt + SupplierInvoice aynı fiziksel quantity'yi iki kez etkileyebilir.

Koruma: source lineage + deterministic source-effect identity + DB unique + integration tests.

## R8 — POS double cari effect
Gross POS tahsilatı cariyi azaltır; bankaya net settlement geldiğinde cari tekrar azaltılamaz. Komisyon ayrı gider/treasury effect'tir.

## R9 — Çek/Senet double effect
Instrument tesliminde cari etkisi verilip bankada tahsil/ödeme anında tekrar effect yazılamaz. Bounce/unpaid reversal ile açılır.

## R10 — Quantity cap ihlali
Over-dispatch, over-invoice, over-receipt veya over-return race condition ile oluşabilir.

Koruma: row lock/atomic update + DB constraint/unique defense + concurrency tests.

## R11 — SQLite test yanılsaması
Production PostgreSQL ise CI PostgreSQL 18 ile çalışır. Locking/numeric/constraint/query davranışı gerçek motorla doğrulanır.

## R12 — Master değişince geçmiş belge değişmesi
Posted document gerekli cari/ürün/fiyat/KDV/kur snapshot'ını tutar. Current master ile geçmiş yeniden hesaplanmaz.

## R13 — Search authorization leak
Global search farklı company/private entity ID'lerini sızdırabilir.

Koruma: company-scoped search + entity fetch permission re-check.

## R14 — API / provider secret leakage
Secret/token UI, log, audit veya API read response'ta plaintext görünebilir.

Koruma: encryption + masked read-back + redaction + rotate action.

## R15 — Marketplace duplicate order
Webhook/poll/retry aynı external order'ı iki kez oluşturabilir.

Koruma: provider-account scoped external entity identity + ayrı inbound message dedupe + DB uniqueness.

## R16 — Stale marketplace retry
Eski stock/price retry güncel provider state'ini geriye götürebilir.

Koruma: current-desired-state outbox semantics + version/staleness control.

## R17 — B2B price divergence
Ayrı B2B fiyat/iskonto truth'u cari ticari koşuluyla ayrışabilir.

Koruma: tek satış fiyatı + Cari İskontosu.

## R18 — Bank import duplicate
Aynı ekstre tekrar yüklenince duplicate bank/cari movement oluşabilir.

Koruma: stable statement-row identity/fingerprint; `Daha Önce Aktarıldı`; match ikinci movement yaratmaz.

## R19 — Production scope creep
Basit reçeteli üretim routing/work-center/OEE/ECO/MRP platformuna dönüşebilir.

Koruma: `Reçete → Emir → Malzeme Çıkışı → Mamul Girişi → Tamamla`.

## R20 — İthalat cost double allocation
Aynı cost item container ve genel seviyede iki kez dağıtılabilir.

Koruma: source identity + allocation totals reconciliation.

## R21 — Maliyet double-count
Purchase price difference ile FX difference veya late landed cost aynı ekonomik farkı iki kez maliyete yazabilir.

Koruma: original receipt/import lineage + deterministic cost effect identity.

## R22 — Report authorization drift
Saved/scheduled report eski permission ile data gönderebilir.

Koruma: her scheduled run'da user/company/permission yeniden kontrol edilir.

## R23 — Backup var, restore yok
Restore drill yapılmamış backup güvence değildir.

Koruma: checksum/version manifest + isolated RestoreRun + Recovery Mode.

## R24 — Recovery sonrası provider duplicate
Restore sonrası Outbox/provider backlog kör çalıştırılırsa geçmiş başarılı işlem tekrar gönderilebilir.

Koruma: recovery barrier + ambiguous outcome reconciliation + staleness/idempotency check.

## R25 — Migration duplicate / side effect
Legacy import duplicate ledger yaratabilir veya geçmiş kayıt için SMS/e-belge/marketplace gönderimi tetikleyebilir.

Koruma: stable source identity + historical side-effect suppression + reconciliation.

## R26 — Main'e büyük kırık blok bırakma
Birden fazla modülü tek dev değişiklikte bitirmeye çalışmak regresyon riskini artırır.

Koruma: `16_UYGULAMA_SIRASI_MILESTONE.md` küçük vertical slice + atomic commit + touched Gate A/B/C.

## R27 — Yetki yalnız UI'da
Gizlenen buton güvenlik değildir. Server-side policy/action kontrolü zorunludur.

## R28 — Plan drift
Kod V16.3/locked plan ile çelişirse sessiz legacy davranış korunmaz. Plan kararı değiştirilir veya kod plana döndürülür.

## MarsEski kullanım kuralı
`MarsEski`:
- business edge-case kaynağı
- migration eşleme kaynağı
- test senaryosu kaynağı
olabilir; yeni application runtime bağımlılığı ve kod tabanı değildir.
