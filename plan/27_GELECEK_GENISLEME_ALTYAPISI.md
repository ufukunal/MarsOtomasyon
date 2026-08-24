# 27 — Gelecek Genişleme Altyapısı V4.2

Bu belge MarsOtomasyon'a ileride yeni özellik eklerken çekirdek finans/stok doğruluğunu bozmadan nasıl genişleneceğini tanımlar.

Amaç bugünden yarım modüller veya kullanılmayan tablolar üretmek değildir. Amaç **düşük maliyetli, açık ve test edilebilir extension seam'ler** bırakmaktır.

## 1. Ana ilke — geleceğe hazır, spekülatif değil

Yeni gelecek özelliği için varsayılan kural:

`ihtiyaç → karar/owner → mevcut seam → additive schema → feature gate → vertical slice → test → rollout`

Yasak yaklaşım:
- gelecekte lazım olabilir diye onlarca boş tablo/interface oluşturmak,
- runtime plugin marketplace/framework kurmak,
- generic EAV/custom-field motoru ile business modelini belirsizleştirmek,
- generic workflow/BPM ile açık domain state'lerini gizlemek,
- AI/OCR sonucunu finans/stok authority yapmak,
- yeni modülün ledger tablolarını doğrudan mutate etmesine izin vermek.

Bir seam ilk gerçek consumer gelmeden fiziksel tablo/package olmak zorunda değildir. Çoğu seam başlangıçta naming/contract/test convention olarak kalabilir.

---

# A. CROSS-CUTTING EXTENSION SEAMS

## 2. Provider family registry

Mevcut marketplace Provider Registry yaklaşımı ileride aşağıdaki **provider family** türlerine genişleyebilir:
- `marketplace`
- `shipping`
- `payment`
- `e_document`
- `communication_sms`
- `communication_email`
- `communication_whatsapp`
- `exchange_rate`
- `storage`
- `ocr_document_extraction`
- `ai_assistant`
- `accounting_export`
- `feed_discovery`

Ortak registry metadata:
- canonical provider key
- family
- docs/source reference
- verified date
- API/version/auth model
- region/scope
- capability set
- pagination/rate-limit/webhook behavior where relevant
- sandbox/test support
- deprecation/change reference

**Kural:** family ortaklığı business contract ortaklığı anlamına gelmez. Marketplace ile kargo aynı adapter interface'ine zorlanmaz.

## 3. Capability contract

Provider veya modül özelliği tek `supportsEverything` boolean'ı kullanmaz.

Typed capability örnekleri:
- marketplace: listing, stock, price, media, order, return, settlement
- shipping: quote, create_shipment, label, tracking, cancel
- payment: create_payment, refund, settlement, webhook
- e-document: submit, status, cancel, artifact
- feed: product_feed, availability_feed, price_feed
- OCR: pdf/image extraction, confidence, page limits

UI yalnız gerçekten `supported` capability'yi aktif action olarak gösterir.

## 4. Versioned internal event catalog

Outbox eventleri rastgele string olarak büyümez. Her önemli event:
- canonical event name
- schema version
- owner module
- semantic class (`IMMUTABLE_EVENT_SNAPSHOT` / `CURRENT_DESIRED_STATE` veya explicit equivalent)
- allowed payload fields
- consumer compatibility policy

taşır.

Örnek gelecek eventleri:
- `sales.invoice.posted.v1`
- `inventory.stock.changed.v1`
- `commerce.product.publish_requested.v1`
- `treasury.movement.posted.v1`
- `returns.received.v1`

Bu **Event Sourcing değildir**. Transactional authority yine source tablolar/ledger'lardır.

## 5. External identity contract

Yeni integration family'ler stable external identity kullanır:
`company + provider_family + provider + account/connection + entity_type + external_id`.

Ancak tek dev universal `external_refs` tablosu bugünden kurulmaz. İki veya daha fazla domain gerçekten aynı storage contract'ını paylaşmadan önce domain-specific mapping tercih edilir.

Paylaşılan şey önce **identity ve idempotency sözleşmesi**dir, tablo değildir.

## 6. Feature rollout / capability gate

Yeni özellikler UI'ya bir anda dağılmaz.

Minimum seam:
- canonical `feature_key`
- application-level enabled/disabled
- gerektiğinde company-level override
- permission kontrolünden ayrı feature availability
- disabled feature için dead route/button yok

Company-level DB flag yalnız ilk gerçek per-company rollout ihtiyacı geldiğinde eklenir. M0/M1'de code/config based registry yeterlidir.

## 7. Approval seam

Mevcut four-eyes yaklaşımı ileride kritik işlemlerde ortak capability olarak kullanılabilir:
- fiyat/risk limiti değişikliği
- büyük stok adjustment
- yüksek tutarlı ödeme
- purchase approval
- import cost finalization

Generic BPM kurulmaz. Ortak `ApprovalRequest/Decision` modeli ancak en az iki gerçek use-case aynı contract'ı paylaştığında çıkarılır.

## 8. Import/parser registry

Bulk import pipeline yeni formatlar için parser adapter seam taşır:
- CSV/XLSX
- MT940
- UBL/XML
- provider settlement files
- carrier files
- legacy exports

Parser yalnız normalize DTO üretir; business posting'i bypass etmez.

## 9. Report registry

Hazır raporlar stabil `report_key` + owner + input schema + output contract ile kayıt edilir.

Yeni modül kendi raporlarını registry'ye ekler. Generic SQL/report designer kurulmaz.

---

# B. PRODUCT / CATALOG FUTURE SEAMS

## 10. Ürün ailesi / varyant altyapısı

Bugünkü `Product` satılabilir ve stok tutulabilir SKU olmaya devam eder.

İleri ihtiyaç için opsiyonel seam:
- `ProductFamily`
- `VariantDimension` (örn. Renk, Ölçü)
- `ProductVariantRelation` / family membership
- family-level shared content/media metadata where useful

**Kritik karar:** stok, fiyat, barkod ve maliyet authority yine `Product/SKU` seviyesinde kalır. Family kendi başına stok authority olmaz.

Bu sayede:
- Trendyol/Amazon variant grouping,
- renk/ölçü aileleri,
- ortak ürün açıklaması,
- kanalda parent-child listing
ileride çekirdeği yeniden yazmadan eklenebilir.

V1'de gerçek variant ihtiyacı yoksa bu tablolar oluşturulmaz; yalnız mapping contract `Product/SKU` ile çalışır.

## 11. Localized product content

Uluslararası kanal ihtiyacında ileride:
- locale
- title
- description
- technical text
- SEO/channel content
saklanabilir.

Core Product adı tek truth olmaya devam eder; localized content ayrı content capability'dir.

## 12. Bundle / kit / set

İleride sanal set/kit satışı gerekirse:
- commercial bundle relation
- component quantities
- availability calculation
- order explosion policy
ayrı capability olarak eklenebilir.

Production BOM ile commerce bundle aynı kavram değildir; aynı tabloya zorlanmaz.

## 13. Reorder / replenishment planning

İleride:
- min/max stok
- reorder point
- preferred supplier
- lead time
- suggested purchase quantity
read-model/planning capability'si eklenebilir.

Öneri otomatik PurchaseOrder authority değildir; kullanıcı onayıyla gerçek purchase use-case'e dönüşür.

---

# C. DEPO / OPERASYON FUTURE SEAMS

## 14. Mobil depo / PWA / scanner

Mevcut barcode/QR ve server-side use-case'ler ileride:
- mobil kabul
- toplama
- sevk
- transfer kabul
- sayım
- fason teslim
uygulamasına açılabilir.

Mobil istemci ayrı business engine olmaz. Aynı `/api/v1` veya dedicated versioned operational DTO/action'ları kullanır.

Offline write senkronizasyonu V1 seam değildir; gerçek ihtiyaçta conflict/idempotency tasarımıyla ayrıca ele alınır.

## 15. Barkod / etiket baskı

Printing capability ileride:
- A4 label
- thermal label
- ZPL/TSPL gibi printer format adapterları
- product/warehouse/shipment label
ile genişleyebilir.

Business barcode identity Product/Barcode modelinde kalır; printer formatı authority değildir.

## 16. Wave picking / packing

E-ticaret hacmi artarsa:
- pick batch/wave
- packing station
- package split/merge
- scan verification
- shipment handoff
operasyon capability'si eklenebilir.

SalesOrder ve stock authority değişmez.

## 17. Lot/seri geleceği

V1 core lot/seri yoktur. İleride gerçek regülasyon/garanti ihtiyacı çıkarsa additive migration ile:
- tracking policy
- lot/serial identity
- stock movement allocation
- traceability
ayrı milestone olur.

Bugünden nullable `lot_id` kolonları her tabloya eklenmez.

---

# D. SATIŞ / CRM / SATIŞ SONRASI

## 18. Hafif CRM

İleride:
- lead
- opportunity
- activity
- follow-up
- sales owner
- pipeline
modülü eklenebilir.

CRM Account master'ını kullanır fakat `account_transactions` authority'ye dokunmaz. Generic marketing automation platformu değildir.

## 19. Garanti / servis / kurulum

Aydınlatma ve fiziksel ürün operasyonları için ileri aday:
- warranty case
- service request
- installation appointment
- repair/replacement
- technician/partner assignment
- parts/material usage
- customer communication

SalesInvoice/SalesOrder/Product lineage kullanılır. Stok parçası harcanıyorsa normal StockMovement use-case'i çağrılır.

## 20. Sözleşme / periyodik işlem

Gerçek ihtiyaçta recurring service/order/payment schedule ayrı module olabilir. Tekrarlayan job otomatik finansal posting yetkisi kazanmaz; her occurrence normal use-case/idempotency kurallarına uyar.

---

# E. FINANS / DIŞ MUHASEBE / ÖDEME

## 21. Payment gateway adapters

İleride PayTR, iyzico, Stripe vb. için provider family:
- payment/refund
- transaction status
- webhook
- settlement
- fee evidence

eklenebilir.

Payment provider callback doğrudan Account/Treasury ledger mutate etmez; normalize source → finance use-case kullanır.

## 22. Bank API / open-banking

V1 dışında kalmaya devam eder. İleride eklendiğinde:
- connection/account mapping
- transaction cursor
- stable external row identity
- reconciliation
- read-only first rollout
kullanılır.

Canlı banka feed'i `treasury_movements` authority değildir.

## 23. Dış muhasebe / ERP export adapters

İleride Logo, Mikro, Netsis, Luca veya başka muhasebe/ERP sistemlerine:
- account/customer export
- invoice/e-document reference
- payment/treasury export
- inventory summary/document export
provider adapterları eklenebilir.

Mars business ledger'ı dış sistem callback'iyle keyfi overwrite edilmez. Integration state ayrı mapping/evidence olarak tutulur.

## 24. Bütçe / nakit akışı tahmini

Forecast/read model olarak eklenebilir:
- expected collections/payments
- order commitments
- historical trend
- marketplace payout expectations

Tahmin gerçek TreasuryMovement değildir.

---

# F. E-TİCARET / DIŞ KANAL GENİŞLEMELERİ

## 25. Shipping provider family

İleride kargo adapter contract:
- service/reference data
- shipment quote where available
- create shipment
- tracking number
- label
- tracking events
- cancel
- return shipment

Provider shipment state Mars Dispatch lifecycle'ını keyfi overwrite etmez; mapping/normalization kullanılır.

## 26. Feed / discovery channels

Google Merchant Center, Meta Catalog, Akakçe, Cimri vb. **marketplace değildir**.

Ayrı `feed_discovery` family:
- product feed
- stock/availability
- price
- media URLs
- diagnostics

taşır.

Order/import/settlement capability'si varmış gibi gösterilmez.

## 27. Social commerce / messaging commerce

WhatsApp/Instagram vb. ileride order source olacaksa Communication ile Marketplace modelleri birbirine karıştırılmaz. Mesajdan sipariş oluşturma ayrı application use-case olur ve kullanıcı/human confirmation policy taşır.

## 28. Marketplace promotion/ads evidence

İleride provider desteklerse:
- campaign/ad spend
- promotion discount contribution
- coupon/platform subsidy
read/evidence olarak alınabilir.

Provider marketing datası product price authority değildir.

---

# G. DOCUMENT CAPTURE / OCR / AI

## 29. Document extraction pipeline

Fatura, dekont, ekstre, kargo etiketi veya teknik doküman için gelecekte:

`Attachment → ExtractionJob → ExtractedFields + confidence → Human Review → Domain Action`

seam'i kullanılabilir.

OCR/extraction sonucu doğrudan:
- invoice post,
- payment post,
- stock movement,
- account transaction
oluşturamaz.

Finans/stok effect her zaman normal validated use-case ile oluşur.

## 30. AI provider seam

AI yalnız yardımcı capability'dir. Provider-neutral adapter ileride:
- ürün açıklaması/başlık önerisi
- marketplace içerik uyarlama
- kategori/özellik eşleme önerisi
- banka eşleştirme önerisi
- ithalat liste normalizasyon önerisi
- belge/OCR alan doğrulama
- anomali/risk uyarısı
- müşteri mesaj taslağı
- rapor özeti
üretebilir.

Zorunlu güvenlik:
- company scope
- PII/secret redaction policy
- prompt/template version
- model/provider metadata
- input/output size limit
- human review where business effect possible
- no hidden autonomous ledger mutation
- audit/reference where recommendation business decision'a dönüşür

AI sonucu **authority değildir**.

## 31. AI / OCR confidence ve review queue

Suggestion/extraction:
- source artifact
- field/value
- confidence
- provider/model/version
- accepted/rejected/corrected
- reviewer
metadata taşıyabilir.

Bu generic BPM değildir; yalnız extraction/suggestion review lifecycle'dır.

---

# H. ANALYTICS / BI / FORECAST

## 32. BI / data export

İleride external BI gerekiyorsa operational DB'yi üçüncü taraf sorgularına doğrudan açmak yerine:
- curated read model
- scheduled export
- read-only replica ancak ölçülmüş ihtiyaçta
- field/PII allow-list
kullanılır.

## 33. Demand forecasting

Satış geçmişi, stok, lead time ve channel data üzerinden suggestion üretilebilir. Forecast hiçbir zaman otomatik stock/account ledger değildir.

## 34. Anomaly detection

Read-model üzerinden:
- sıra dışı fiyat
- beklenmeyen stok düşüşü
- duplicate settlement şüphesi
- margin anomaly
- geciken purchase/dispatch
uyarısı üretilebilir.

Alert business correction değildir.

---

# I. SECURITY / IDENTITY FUTURE SEAMS

## 35. SSO / enterprise identity

İleride OIDC/SAML/LDAP entegrasyonu gerekirse internal User identity provider adapterı eklenebilir. Company membership/RBAC Mars authority olarak kalır.

## 36. Passkey / stronger auth

Password + 2FA üzerine WebAuthn/passkey eklenebilir. B2B ve internal auth context'leri yine ayrı kalır.

## 37. Outbound webhook subscriptions

Harici sistemlere Mars event'i göndermek gerekirse:
- subscription
- allowed event types
- destination URL
- signing secret
- delivery attempts
- disable/replay
kullanılır.

SSRF/secret/outbox kuralları `04` ve `22`ye tabidir.

---

# J. MULTI-COMPANY / SCALE FUTURE SEAMS

## 38. Intercompany

Company isolation zaten geleceğe uygundur. İleride iki Mars Company arasında işlem gerekiyorsa:
- explicit source company document
- explicit target company document
- paired external/intercompany identity
- iki company ledger'ında ayrı posting
- reconciliation
ile tasarlanır.

Bir şirketin ledger satırı diğer company'ye bağlanarak tenant isolation bypass edilmez.

## 39. SaaS geleceği

V1 SaaS değildir. Company isolation, scoped jobs, provider accounts ve public ID seam'leri ileride SaaS değerlendirmesine engel olmaz.

Ancak subscription/billing/tier/tenant provisioning bugünden kurulmaz.

## 40. Performance scale-up

Öncelik ölçümdür. Sıra:
1. query/index tuning
2. cache/read model
3. queue isolation
4. worker scaling
5. read replica/materialized projection where justified
6. ancak gerçek ihtiyaçta servis ayrımı

Mikroservis ön koşul değildir.

---

# K. BUGÜNDEN HAZIRLANACAK DÜŞÜK MALİYETLİ ALTYAPI

Aşağıdakiler V1 codebase'e gerçek consumer çıktıkça erken eklenebilir ve gelecekte yüksek yeniden yazım maliyetini azaltır:

1. Provider Registry naming/family convention.
2. Typed capability convention.
3. Versioned internal event-name/schema convention.
4. Stable source-effect/external identity conventions.
5. Code/config based FeatureKey registry.
6. Report key registry.
7. Import parser contract.
8. Attachment → processing-job → reviewed-result pattern.
9. Product SKU'nun family/variant grouping'e sonradan bağlanabilmesini engellemeyen PK/identity modeli.
10. Public API DTO/Resource boundary.
11. Correlation ID + audit + Outbox context.
12. Read-model'ların authority olmaması ve rebuild edilebilirliği.

**Bunlar için kullanılmayan fiziksel tablo/interface üretmek zorunlu değildir.** İlk gerçek consumer geldiğinde en dar implementation yapılır.

---

# L. POST-V1 ÖZELLİK ADAYLARI — ÖNCELİK

## Yüksek değer / düşük-orta mimari risk
- kargo adapterları
- payment gateway settlement adapters
- feed channels (Google/Meta/Akakçe/Cimri)
- mobil depo/scanner
- barkod/etiket baskı
- ürün ailesi/varyant grouping
- reorder önerileri
- OCR belge okuma + human review
- dış muhasebe export adapterları
- satış sonrası servis/garanti

## Orta değer / kontrollü sonra
- hafif CRM
- localized content
- wave picking/packing
- budgets/cash-flow forecast
- AI içerik/matching/anomaly assistant
- BI curated exports
- SSO/passkey
- outbound webhook subscriptions

## Yüksek karmaşıklık — gerçek ihtiyaç olmadan yapılmayacak
- lot/seri tracking
- open banking write flows
- offline-first sync
- generic workflow/BPM
- generic custom-field/EAV platformu
- full MRP/APS
- intercompany automation
- SaaS billing/tier
- microservice decomposition

---

# M. FUTURE FEATURE ACTIVATION CHECKLIST

Yeni ileri özellik plan kapsamına alınmadan:
- [ ] Gerçek kullanıcı/business ihtiyacı var mı?
- [ ] Owner module belli mi?
- [ ] Hangi authority ledger/source etkileniyor?
- [ ] Mevcut seam yeterli mi?
- [ ] Yeni provider ise registry/capability doğrulandı mı?
- [ ] External identity/idempotency tanımlı mı?
- [ ] Transaction/outbox sınırı belli mi?
- [ ] Company/auth scope belli mi?
- [ ] Additive migration planı var mı?
- [ ] Feature rollout/disable yolu var mı?
- [ ] V16.3 veya sonraki onaylı UI contract güncellendi mi?
- [ ] Test/DoD eklendi mi?
- [ ] Recovery/backup/migration etkisi değerlendirildi mi?

Bu checklist geçmeden geleceğe yönelik fikir production code'a dağılmaz.
