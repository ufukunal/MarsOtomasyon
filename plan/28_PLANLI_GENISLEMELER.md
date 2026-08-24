# 28 — Planlı Genişlemeler V4.2

Bu belge, `27_GELECEK_GENISLEME_ALTYAPISI.md` içindeki adaylardan **resmî roadmap kapsamına alınan** özellikleri tanımlar.

Bu özellikler V1 production gate'i olan M24'ü bloklamaz. Varsayılan geliştirme sırası M24 sonrası M25–M32'dir. Ancak temel extension seam'leri M0–M3 sırasında hazırlanır.

## Resmî planlı genişleme seti
1. Kargo API Adapterları
2. Mobil Depo / Scanner
3. Barkod / Termal Etiket
4. Product Family / Variant
5. OCR Fatura / Dekont Okuma
6. Hafif CRM
7. BI Export
8. CAD / 3D Viewer

Ana kural: Bu modüllerden hiçbiri yeni cari, stok veya treasury authority kurmaz.

---

## 1. Product Family / Variant

### Amaç
Aynı ürün ailesindeki renk, ölçü, çap, gövde rengi, cam tipi gibi seçenekleri marketplace parent/child yapılarıyla uyumlu şekilde gruplayabilmek.

### Domain modeli
- `Product` satılabilir/stoklanabilir SKU olmaya devam eder.
- `ProductFamily`
- `VariantDimension`
- `VariantValue`
- `ProductVariantRelation`
- gerekirse family-level content/media relation

### Authority
- stok: Product/SKU
- barkod: Product/SKU
- fiyat: Product/SKU
- maliyet: Product/SKU
- family yalnız grouping/content authority'sidir.

### UI
`Ürün/Stok` altında mevcut ürün detayını bozmadan:
- Ürün Ailesi
- Varyantlar
- Boyut/Değer tanımları
- Aile içi ürünler
- kanal parent/child mapping görünümü

Simple Product family olmadan çalışmaya devam eder.

### Entegrasyon
Marketplace adapterı provider'ın parent/variant modelini `ProductFamily + Product/SKU mapping` ile bağlar. Provider variant kimliği Mars Product ID yerine geçmez.

### Kabul
- existing Product ID/SKU migration ile değişmez
- family silinmesi SKU/stok history silmez
- duplicate dimension/value guard
- marketplace mapping deterministic
- search/media/report davranışı testli

---

## 2. Barkod / Termal Etiket

### Amaç
Ürün, depo, lokasyon, koli/paket ve sevkiyat operasyonlarında hızlı fiziksel etiket üretimi.

### Kapsam
- A4 etiket
- termal etiket
- ZPL
- TSPL/ESC-POS benzeri format adapterları ihtiyaçta
- ürün barkodu
- depo/lokasyon etiketi
- koli/paket etiketi
- sevkiyat etiketi
- tekrar yazdırma + audit

### Domain sınırı
Barcode kimliği mevcut `Barcode` modelindedir. Etiket şablonu veya printer dili business identity değildir.

### Altyapı
- `LabelTemplate` yalnız gerçek kullanımda
- `LabelRenderRequest`
- `PrinterProfile` / output format config
- server-side render
- print/download audit where needed

Browser doğrudan yazıcıya zorunlu business dependency olmaz. Yerel yazıcı köprüsü gerekirse Scanner Agent benzeri localhost adapter olarak eklenebilir.

### Kabul
- barcode data ile render edilen data eşleşir
- ZPL/PDF snapshot fixture
- yanlış company ürün etiketi üretilemez
- tekrar render business record mutate etmez

---

## 3. Mobil Depo / Scanner

### Amaç
Telefon/tablet/el terminali üzerinden barkod odaklı depo operasyonları.

### İlk kapsam
- ürün arama/scanner
- Mal Kabul
- toplama
- sevk doğrulama
- depo transfer çıkışı/kabulü
- stok sayımı
- fason gönderim/kabul
- lokasyon doğrulama

### Mimari
Mobil/PWA ayrı business engine değildir.

Akış:
`Mobile Client → versioned operational API/DTO → mevcut application use-case → PostgreSQL transaction`.

### Idempotency
Her mutating mobil işlem stabil `client_operation_id/idempotency_key` taşır. Network retry ikinci StockMovement veya document effect üretmez.

### Offline
İlk sürüm online-first'tür. Offline write ancak ayrı milestone ile conflict/reconciliation tasarımından sonra açılır.

### UX
- scanner focus
- büyük dokunma hedefleri
- ses/titreşim feedback
- hızlı quantity input
- son okutulan ürün
- duplicate scan policy
- connection/error state

### Güvenlik
- company scope
- kullanıcı/permission
- kısa ömürlü session/token policy
- device metadata audit where needed

---

## 4. Kargo API Adapterları

### Amaç
Sevkiyat belgesinden provider shipment oluşturmak, barkod/etiket almak ve tracking durumunu izlemek.

### Provider family
`shipping`

### Capability örnekleri
- `services_read`
- `quote_read`
- `shipment_create`
- `shipment_cancel`
- `label_read`
- `tracking_read`
- `tracking_webhook`
- `return_shipment_create`

### Akış
`Mars Dispatch → Shipping Request → Outbox → Provider → External Shipment Mapping → Tracking Evidence`.

Provider callback Mars Dispatch lifecycle'ını doğrudan overwrite etmez; normalized application action kullanır.

### Veri
- ShippingConnection
- ExternalShipmentMapping
- external tracking number
- provider label artifact/reference
- provider shipment status evidence
- ProviderAttempt/problem center

### Kurallar
- aynı Dispatch için duplicate shipment create engeli
- timeout/ambiguous result için query-before-retry
- credential encrypted/masked
- provider capability yoksa UI action disabled/manual
- return shipment M12 Return Core lineage'ına bağlanabilir

### UI
- Sevkiyat detail: `Kargo Oluştur`, `Etiket`, `Takip`, `İptal`
- `Ayarlar → Entegrasyonlar → Kargo`
- teknik provider hata detayları normal kullanıcıya ham gösterilmez

---

## 5. OCR Fatura / Dekont Okuma

### Amaç
PDF/görsel belgelerden veri çıkararak manuel veri girişini azaltmak.

### İlk belge türleri
- alış faturası
- satış/tedarikçi faturası referansı
- banka dekontu
- gider fişi/fatura
- banka ekstresi yardımcı okuma

### Pipeline
`Attachment → ExtractionJob → ExtractedFields → Confidence → Human Review → Draft Domain Action`.

OCR sonucu doğrudan post/finalize yapamaz.

### Çıkarılabilecek alanlar
- belge no/tarih
- VKN/TCKN
- firma/unvan
- para birimi
- ara toplam/KDV/genel toplam
- satır ürün açıklaması/adet/fiyat/KDV
- IBAN/banka/reference

### Matching
- cari eşleme önerisi
- ürün eşleme önerisi
- banka hareketi eşleme önerisi

Öneri otomatik master merge değildir.

### Güvenlik
- attachment authorization
- PII/secret redaction policy
- provider/model/version metadata
- page/file size limit
- confidence threshold
- accepted/rejected/corrected history

### Kabul
- low confidence review zorunlu
- duplicate upload/post guard
- OCR output ledger yazamaz
- accepted result normal Invoice/Expense/Bank use-case validasyonundan geçer

---

## 6. Hafif CRM

### Amaç
Cari ile ticari fırsat ve takip süreçlerini basit şekilde yönetmek.

### Kapsam
- Lead
- Opportunity/Fırsat
- Activity
- Follow-up/Görev
- satış sorumlusu
- pipeline stage
- teklif/sipariş/cari bağlantısı
- görüşme/not/dosya
- reminder/due date

### Sınır
CRM generic marketing automation/BPM değildir.

CRM:
- Account masterını kullanabilir
- teklif oluşturma use-case'ine geçebilir
- `account_transactions` yazamaz
- stok/rezerve authority değildir

### Lead → Cari
Lead'in Account'a dönüşümü explicit kullanıcı action'ıdır. Fuzzy duplicate yalnız öneri verir.

### UI
Ana navigasyona yeni top-level zorunlu değildir. İlk tasarım:
`Cariler → CRM / Fırsatlar` secondary workspace.

V16.3 değişikliği gerekiyorsa ayrı UI approval ile yapılır.

### Kabul
- company/owner scope
- stage history
- activity audit
- lead conversion duplicate guard
- deleted/cancelled opportunity financial effect üretmez

---

## 7. BI Export

### Amaç
Power BI, Metabase, Looker Studio veya kurum içi analitik sistemlere kontrollü veri sağlamak.

### İlke
Üçüncü taraf BI aracı operational PostgreSQL tablolarına sınırsız doğrudan erişmez.

### İlk kapsam
- curated read-model/export datasets
- CSV/XLSX/JSON/Parquet where justified
- scheduled dataset export
- incremental watermark where useful
- expiring authorized artifact/download
- PII/field allow-list

### Dataset örnekleri
- sales fact
- purchase fact
- stock snapshot/movement
- account balance/movement
- treasury movement
- marketplace contribution/settlement
- product/channel performance
- production/import summary

### İleri ölçek
Gerçek ölçülmüş ihtiyaçta:
- dedicated read-only DB user
- materialized analytics views
- read replica
- object-storage dataset

eklenebilir.

### Authority
BI dataset/read model hiçbir zaman business ledger değildir. BI'dan gelen write-back V1 kapsamında yoktur.

### Kabul
- dataset schema version
- company scope
- PII masking
- totals authoritative reports ile reconcile
- scheduled export runtime authorization
- failed/partial export gözlemlenebilir

---

## 8. CAD / 3D Viewer

### Amaç
Ürün, ithalat, üretim, fason ve teknik dosyalardaki CAD/3D kaynaklarını Mars içinde read-only inceleyebilmek.

### Format hedefi
İlk gerçek kullanım fixture'larına göre önceliklendirilir:
- DWG / DXF
- MAX / 3DS
- FBX / OBJ / STL
- STEP/STP / IGES
- IFC/RVT/DWF gerektiğinde provider capability'sine göre
- web derivative olarak glTF/GLB where suitable

### Mimari
`Original Attachment → Derivative/Translation Job → Preview Artifact/Manifest → Web Viewer`.

Orijinal dosya authority'dir. Derivative yeniden üretilebilir preview'dır.

### Provider stratejisi
- Autodesk APS Model Derivative + Viewer SDK ana cloud aday
- ODA SDK/Web SDK özellikle DWG/DXF ve self-hosted/gizlilik ihtiyacında alternatif
- seçilmiş interchange formatlarda kontrollü local/server converter + glTF/GLB viewer mümkün

Tek provider hard-code edilmez; `cad_viewer/model_derivative` family + capability contract kullanılır.

### `.MAX` kuralı
Mars proprietary `.max` parser yazmaz. `.max` preview Autodesk derivative veya kontrollü Autodesk/3ds Max automation/export yoluyla oluşturulur.

### İlk UI
Attachment yanında `3D/CAD Önizle` action.

2D desteklenirse:
- pan/zoom
- sheet/layout
- layer visibility
- object properties
- measure where supported

3D desteklenirse:
- orbit/pan/zoom
- fit model
- object tree
- hide/isolate
- property inspect
- section/measure where supported

### Güvenlik
- cloud provider'a CAD yükleme company-level explicit policy
- private attachment authorization
- derivative access controlled/short-lived
- source checksum lineage
- normalized conversion errors
- provider credential masking/redaction

### Kabul
- original file derivative failure'dan etkilenmiyor
- source checksum → derivative mapping deterministic
- duplicate translation idempotent
- cross-company viewer access BLOCK
- gerçek DWG/DXF ve seçilmiş 3D fixture browser viewer'da açılıyor
- `.max` desteği provider/conversion sonucu olarak ifade ediliyor; native parser iddiası yok
- editing/authoring yok

Ayrıntı: `30_DOSYA_ONIZLEME_CAD_3D.md`.

---

# Milestone sırası

Varsayılan post-V1 sıra:

`M25 Product Family/Variant → M26 Barkod/Termal Etiket → M27 Mobil Depo/Scanner → M28 Kargo API Adapterları → M29 OCR Belge Okuma → M30 Hafif CRM → M31 BI Export → M32 CAD/3D Viewer`

Bağımsız bir milestone öne alınabilir; ancak dependency ve `27` activation checklist'i ihlal edilemez.

## M25 önkoşul
M3 Product/SKU identity ve M17 marketplace mapping stabil olmalı.

## M26 önkoşul
M3 Barcode + Files/Printing foundation stabil olmalı.

## M27 önkoşul
M4/M7/M9 depo use-case'leri ve `/api/v1` operational auth/idempotency yolu stabil olmalı.

## M28 önkoşul
M7 Dispatch + M17 provider registry/outbox/problem-center stabil olmalı; provider gerçek API contract'ı doğrulanmalı.

## M29 önkoşul
Files/Attachment security + import parser + extraction/review pattern hazır olmalı.

## M30 önkoşul
M2 Account + M5 Quote authority stabil olmalı.

## M31 önkoşul
M13 report/read-model registry ve export job altyapısı stabil olmalı.

## M32 önkoşul
V1 PDF/görsel viewer ve Attachment security stabil olmalı. Kullanılacak CAD/3D provider/lisans modeli, cloud upload policy ve gerçek format fixture'ları doğrulanmış olmalı.

# Ortak DoD
Her planlı genişleme:
- FeatureKey ile kontrollü rollout
- company scope
- server-side authorization
- additive migration
- source-effect/idempotency
- audit/observability
- failure path
- PostgreSQL integration tests
- V16.3 veya sonraki onaylı UI contract
- backup/migration impact review
- ilgili `14_TEST_CI_KALITE.md` ve `18_DEFINITION_OF_DONE.md` koşulları
sağlanmadan tamamlanmış sayılmaz.
