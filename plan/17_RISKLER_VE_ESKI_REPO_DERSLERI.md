# 17 — Riskler ve MarsEski Dersleri V4.2

## R1 — Scope creep / aşırı mimari
Ön muhasebe uygulamasını generic ERP/QMS/PLM/BPM platformuna çevirmek en büyük kapsam riskidir.

Koruma: V16.3 UI, basit üretim, bakiye bazlı cari, tek fiyat, lot/seri core yok, generic designer/QMS/shop-floor yok, tek sunucu/az kullanıcı hedefi.

## R2 — Legacy code taşıma
`MarsEski` application code blok halinde copy/paste edilmez. Yalnız business rule, edge-case, test ve migration bilgisi alınır.

## R3 — UI prototipini domain sanmak
Prototype/localStorage/demo renderer production truth değildir. V16.3 görsel/akış sözleşmesidir; data ve action Laravel backend'den gelir.

## R4 — Generic renderer / dead button
Generic `Durum/Açıklama/Kaydet` formu veya no-op button kabul edilmez. Screen-specific acceptance test gerekir.

## R5 — Transaction parçalanması
Fatura/dispatch/finance posting yarım ledger bırakamaz. Kullanıcı açısından tek business action tek transaction + outbox sınırına uyar.

## R6 — Cari modelinin OpenItem'a dönmesi
Invoice allocation/paid-partial settlement eklenmesi yasaktır. `account_transactions` cari authority'dir.

## R7 — Duplicate stock effect
Dispatch + Invoice veya GoodsReceipt + SupplierInvoice aynı fiziksel quantity'yi iki kez etkileyebilir.

Koruma: K-041 source-effect matrix + lineage + unique + integration tests.

## R8 — POS double cari effect
Gross POS tahsilatı cariyi azaltır; bankaya net settlement geldiğinde cari tekrar azaltılamaz. Komisyon ayrı gider/treasury effect'tir.

## R9 — Çek/Senet double effect
Instrument tesliminde cari etkisi verilip bankada tahsil/ödeme anında tekrar effect yazılamaz. Bounce/unpaid reversal ile açılır.

## R10 — Quantity cap ihlali
Over-dispatch, over-invoice, over-acceptance, over-cancel veya over-return race condition ile oluşabilir.

Koruma: row lock/atomic update + DB defense + concurrency tests.

## R11 — SQLite test yanılsaması
Production PostgreSQL ise CI PostgreSQL 18 ile çalışır.

## R12 — Master değişince geçmiş belge değişmesi
Posted document gerekli cari/customer/ürün/fiyat/KDV/kur snapshot'ını tutar. Current master ile geçmiş yeniden hesaplanmaz.

## R13 — Search authorization leak
Global search farklı company/private entity ID'lerini sızdırabilir.

Koruma: company-scoped search + entity fetch permission re-check.

## R14 — API / provider secret leakage
Secret/token UI, log, audit veya API read response'ta plaintext görünebilir.

Koruma: encryption + masked read-back + redaction + rotate action.

## R15 — Marketplace duplicate order
Webhook/poll/retry aynı external order'ı iki kez oluşturabilir.

Koruma: provider-account external identity + inbound message dedupe + DB uniqueness.

## R16 — Stale marketplace retry
Eski stock/price/media retry güncel provider state'ini geriye götürebilir.

Koruma: current-desired-state semantics + version/staleness control.

## R17 — B2B price divergence
Ayrı B2B fiyat/iskonto truth'u cari ticari koşuluyla ayrışabilir.

Koruma: tek net satış fiyatı − Cari İskontosu.

## R18 — Bank import duplicate
Aynı ekstre tekrar yüklenince duplicate bank/cari/treasury movement oluşabilir.

Koruma: stable statement-row identity/fingerprint; match ikinci movement yaratmaz.

## R19 — Production scope creep
Basit reçeteli üretim routing/work-center/OEE/ECO/MRP platformuna dönüşebilir.

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

## R24 — Recovery sonrası provider duplicate
Restore sonrası Outbox/provider backlog kör çalıştırılırsa geçmiş başarılı işlem tekrar gönderilebilir.

Koruma: recovery barrier + reconciliation + staleness/idempotency.

## R25 — Migration duplicate / side effect
Legacy import duplicate ledger yaratabilir veya geçmiş kayıt için external side-effect tetikleyebilir.

Koruma: stable source identity + historical side-effect suppression + reconciliation.

## R26 — Main'e büyük kırık blok bırakma
Birden fazla modülü tek dev değişiklikte bitirmeye çalışmak regresyon riskini artırır.

Koruma: granular vertical slice + atomic commit + Gate A/B/C.

## R27 — Yetki yalnız UI'da
Gizlenen buton güvenlik değildir. Server-side policy/action kontrolü zorunludur.

## R28 — Plan drift
Kod V16.3/locked plan ile çelişirse sessiz legacy davranış korunmaz. Plan kararı değiştirilir veya kod plana döndürülür.

## R29 — Mixed-currency cari bakiyesi
TRY/EUR/USD raw hareketleri tek signed bakiye olarak toplanırsa anlamsız finansal sonuç çıkar.

Koruma: V1 Account tek book currency; base equivalent ayrı analytical snapshot.

## R30 — Transit stok/value kaybı
Warehouse transfer source OUT ile destination IN arasında company quantity/value rapordan kaybolabilir.

Koruma: in-transit custody + carrying value reconciliation.

## R31 — Fason custody expense'e dönüşmesi
Fasona gönderilen company-owned material yanlışlıkla stock OUT/expense gibi kaybedilebilir.

Koruma: subcontract custody quantity + value; gelen/fire/kalan reconcile.

## R32 — Marketplace clearing double-count / eksik payout
Marketplace invoice end-customer Account'a, payout farklı clearing mantığına yazılırsa bakiye kapatılamaz; fee/payout double-count olabilir.

Koruma: legal customer snapshot ≠ financial clearing Account; provider settlement external identity; clearing reconciliation.

## R33 — Marketplace settlement duplicate
Aynı provider payout/commission row tekrar çekildiğinde ikinci Account/Treasury effect oluşabilir.

Koruma: provider-account scoped settlement identity/fingerprint + finance source-effect uniqueness.

## R34 — B2B privilege escalation
External bayi session'ı internal User/RBAC ile paylaşılırsa admin/company verisine yetki sızabilir.

Koruma: separate B2B auth guard/context + default-deny DTO + pre-bound Account.

## R35 — Tax/rounding drift
KDV dahil/hariç, iskonto sırası ve rounding farklı ekran/providerlarda farklı uygulanırsa belge toplamı ve e-belge tutarı ayrışır.

Koruma: K-043 tek tax/discount calculator contract + snapshot + tests.

## R36 — Mal Kabul quality quantity kaybı
Bir line'ın bir kısmı uygun, bir kısmı red iken tek quality label tüm miktarı yanlış available veya PO complete yapabilir.

Koruma: accepted/pending/rejected quantity split; progress yalnız accepted quantity.

## R37 — CI kuralı var ama repository enforce etmiyor
Plan kırmızı CI'ı yasaklarken unprotected main kırık commit kabul edebilir.

Koruma: M0 required checks + branch protection entry/exit gate; M23 production-candidate sırasında tekrar doğrulama.

## R38 — Tasarım referansı versionlanmamış
V16.3 yalnız isim olarak kalırsa zamanla farklı geliştirici farklı prototype'a bakabilir.

Koruma: M0 immutable design artifact veya canonical source/hash reference versioning.

# V4.2 future-extension riskleri

## R39 — Spekülatif plugin/framework şişmesi
“İleride lazım olur” gerekçesiyle runtime plugin loader, universal provider interface, generic hook/BPM veya boş extension tabloları kurulursa bakım maliyeti feature'dan önce gelir.

Koruma: `27_GELECEK_GENISLEME_ALTYAPISI.md`; ilk gerçek consumer yoksa yalnız convention/contract, fiziksel abstraction değil.

## R40 — Marketplace variant modeli Product authority'yi kırması
External marketplace parent/variant modeli doğrudan Mars Product'ı family gibi kullanırsa stok/fiyat/barkod authority belirsizleşir.

Koruma: V1 Product = sellable SKU. Future `ProductFamily/VariantRelation` yalnız grouping/content seam; stock/price/cost Product seviyesinde kalır.

## R41 — AI/OCR autonomous posting
OCR veya AI önerisi doğrudan invoice/payment/stock/account movement yazarsa yanlış extraction finansal truth'a dönüşebilir.

Koruma: `Attachment/Input → ProcessingJob → Suggestion/Confidence → Review → normal Domain Use-Case`; AI/OCR authority değildir.

## R42 — Feature flag ile permission karışması
Feature enabled olması kullanıcıya yetki vermez; permission olması da feature'ın hazır olduğu anlamına gelmez.

Koruma: `FeatureKey availability` ve authorization ayrı kontrol edilir. Disabled feature dead route/button üretmez.

## R43 — Universal external-reference tablosu domain bütünlüğünü yutması
Her provider/domain kimliği tek polymorphic JSON tabloya sıkıştırılırsa unique constraint, FK ve lifecycle doğruluğu zayıflar.

Koruma: önce shared identity convention; family/domain-specific mapping. Universal storage ancak birden fazla gerçek domain aynı contract'ı kanıtladığında.

## R44 — Yeni provider'ın ledger bypass etmesi
Kargo, payment, bank, accounting export veya future integration callback'i doğrudan ledger mutate ederse source-effect/idempotency modeli kırılır.

Koruma: provider evidence/normalized source → owner application use-case → authority ledger.

## R45 — Feed kanalını marketplace sanmak
Google Merchant/Meta/Akakçe/Cimri gibi feed/discovery kanallarına order/return/settlement capability uydurmak yanlış UI ve state üretir.

Koruma: ayrı `feed_discovery` provider family ve typed capability.

## R46 — Mobil/offline duplicate write
Mobil depo istemcisi veya gelecekte offline sync aynı sevk/sayım/transferi birden fazla kez post edebilir.

Koruma: aynı server-side use-case + stable client operation identity + idempotency. Offline-first conflict modeli gerçek ihtiyaç olmadan açılmaz.

## R47 — Generic custom-field/EAV çekirdek business modeline sızması
Vergi, fiyat, stok, miktar veya belge state'i “custom field” olarak modellenirse constraint ve migration güvenliği kaybolur.

Koruma: core business alanı explicit schema'dır. Flexible metadata yalnız non-authoritative, namespaced ve allow-listed ihtiyaçta değerlendirilir.

## R48 — Forecast/suggestion authority drift
Reorder, demand forecast, anomaly veya cash-flow forecast suggestion'ı otomatik posted record gibi ele alınırsa tahmin business truth olur.

Koruma: planning/read-model → explicit user/domain action; ledger yalnız normal use-case ile değişir.

## MarsEski kullanım kuralı
`MarsEski`:
- business edge-case kaynağı
- migration eşleme kaynağı
- test senaryosu kaynağı
olabilir; yeni application runtime bağımlılığı ve kod tabanı değildir.
