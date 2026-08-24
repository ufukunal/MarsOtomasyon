# 12 — Entegrasyon, API, B2B ve İletişim V4.2

## 1. Integration Core
WooCommerce, Trendyol, Hepsiburada, Amazon, n11, PttAVM, idefix, Allesgo ve Mars B2B ayrı business engine değildir. Tek **E-Ticaret Integration Core** üzerine adapter olarak bağlanır.

**V1 doğrulanmış adapter seti:**
- WooCommerce
- Trendyol
- Hepsiburada
- Amazon Selling Partner API — SP-API
- n11
- PttAVM
- idefix
- Allesgo
- Mars B2B

Amazon için ilk operasyon odağı Türkiye marketplace'idir; adapter marketplace/region kimliğini modelde ayrı taşır.

**Deferred / API doğrulama bekleyen adaylar:**
- Çiçeksepeti
- Pazarama
- Koçtaş
- Teknosa
- Temu Türkiye
- Boyner

Deferred provider için güncel resmî API dokümanı veya gerçek seller/partner erişimi doğrulanmadan production adapter yazılmaz, milestone açılmaz ve UI'da aktif kanal gösterilmez.

Mars authority:
- ürün master
- kullanılabilir stok
- temel net satış fiyatı
- cari/B2B ticari kuralı
- Mars SalesOrder
- invoice/e-document internal reference
- account/treasury/stock business ledgers

External kanal operasyon/satış ve evidence kaynağıdır; callback Mars truth'unu doğrulamasız overwrite edemez.

## 2. Adapter contract
Her marketplace adapterı aynı business contract'ın yalnız provider tarafından desteklenen capability'lerini uygular.

Ortak capability başlıkları:
- connection/authentication
- category/attribute/brand/reference-data discovery
- product/listing create/update/status
- product/listing mapping
- product media/image publish/status
- stock publish
- price publish
- order import
- order acknowledgement/status
- shipment/package/cargo operations
- cancellation
- return/claim/RMA
- invoice/e-document reference upload/sync where supported
- product/order questions where supported
- marketplace accounting/settlement/payout evidence where supported
- webhook/notification where supported
- polling/cursor fallback where webhook yoksa

Bir provider capability sunmuyorsa adapter `unsupported` döndürür. Core veya UI bunu başarı gibi emüle etmez.

## 3. Capability matrix
Kanal bazlı gerçek özellik desteği typed config/read-model ile belirlenir.

Örnek flags:
- `catalog_read`
- `product_create`
- `product_update`
- `product_media_write`
- `inventory_write`
- `price_write`
- `orders_read`
- `shipment_write`
- `cancel_write`
- `returns_read_write`
- `invoice_write`
- `questions_read_write`
- `settlement_read`
- `payout_read`
- `webhook`

Capability provider dokümantasyonuna göre adapter implementasyonunda kilitlenir; kullanıcı ayarı provider'ın desteklemediği özelliği açamaz.

## 4. Provider registry — adapter entry gate
Her aktif provider için kod başlamadan repository'de/typed registry'de en az şu metadata bulunur:
- canonical provider key
- resmi developer/docs reference
- verified date
- API version/base contract
- auth model
- marketplace/region scope
- pagination/cursor model
- rate-limit davranışı
- webhook/polling mode
- supported capability matrix
- sandbox/test-account bilgisi where available
- deprecation/change source

Bu kayıt yoksa adapter `verified` kabul edilmez. Credential değeri registry'ye yazılmaz.

## 5. External identity modeli
External entity identity:
`company + provider + internal_channel/account + entity_type + external_entity_id`.

Inbound message identity:
`company + internal_channel/account + provider_message/event_id` veya stabil eşdeğeri.

Settlement identity:
`company + internal_channel/account + settlement/payout/line external identity`.

Bu identity'ler ayrıdır. Aynı provider retry duplicate Mars order/stock/finance effect üretemez.

Provider-specific listing/SKU/order/package/claim/settlement IDs ayrı external mapping olarak saklanır; tek `external_id` alanına sıkıştırılmaz.

## 6. Kanal Merkezi
V16.3 menüsü değişmez:
- Kanal Merkezi
- E-Ticaret Siparişleri
- Ürün Entegrasyonu
- E-Ticaret İadeleri
- Ürün/Sipariş Soruları
- Fatura Entegrasyonu
- Entegrasyon Sorunları
- Kanal Ayarları

Yeni doğrulanmış pazaryerleri ayrı ana menü oluşturmaz; kanal filtresi/kartı olarak aynı çalışma alanına girer.

## 7. Kanal ayarları
Tabs:
`Bağlantı · Ürün · Sipariş · Fatura · Stok · Görsel`.

Provider credential formu adapter-owned schema ile oluşturulur fakat secret davranışı ortaktır.

### WooCommerce
- Kanal Adı
- Site URL
- Consumer Key
- Consumer Secret
- financial settlement mode: `direct_account | clearing_account`

### Trendyol
- Supplier ID
- API Key
- API Secret

### Hepsiburada
Merchant/integrator account bilgileri ve güncel authentication alanları provider registry/adapter schema'ya göre.

### Amazon SP-API
- marketplace/region
- seller/account identity
- application authorization metadata
- LWA/SP-API authorization secrets/tokens encrypted storage

### n11
- seller/app identity
- app key
- app secret

### PttAVM
- merchant identity
- API Key/token veya yürürlükteki authentication alanları

### idefix
- vendor/seller identity
- API Key
- API Secret

### Allesgo
Seller/account identity ve yürürlükteki API credentials adapter config schema ile.

Mars B2B dahiliyse harici secret istemez.

Credential alan adları provider API değiştikçe adapter config schema ile güncellenir; core tabloda sağlayıcıya özel onlarca kolon açılmaz.

## 8. Secret UX / güvenlik
- encrypted-at-rest
- save sonrası gerçek secret UI/API read-back yok
- maskeli görünüm
- `Değiştir` ile rotate
- connection test ayrı use-case
- ham HTTP/JSON exception normal kullanıcıya gösterilmez
- refresh/access token lifecycle secure credential store üzerinden yürür
- provider token/secret Outbox payload'a plaintext yazılmaz

## 9. Sync ilkeleri
- idempotent import/export
- cursor/page state
- retry/backoff
- provider rate limit
- error/dead center
- external mapping
- conflict policy
- last successful sync / last error visibility
- ambiguous result için blind retry yerine query/reconcile
- provider-specific batch/task result polling where required
- request/response schema versioning
- deprecation/change tracking

Rate-limit tek global sabit değildir; provider/account/endpoint capability'sine göre adapter policy uygular.

## 10. E-Ticaret sipariş akışı
`Provider Inbox/Poll → Validate/Dedupe → Customer Snapshot → Mars Sales Order → Reservation → Hazırlama/Sevk → Fatura → Kanal güncelleme`.

Kullanıcı-visible kanal durumları:
`Yeni, Hazırlanıyor, Gönderildi, Tamamlandı, İptal, Sorun`.

Reservation yapılamıyorsa negatif stok yaratılmaz; order `Sorun/Stok Eksik` operasyon reason taşır.

Provider'ın ham status'u evidence olarak saklanabilir; Mars SalesOrder lifecycle ayrı authority'dir.

## 11. Marketplace customer snapshot vs financial counterparty
Marketplace siparişindeki ad/adres/vergi/telefon/e-posta legal/order snapshot'tır. Her external customer için zorunlu Mars Account yaratılmaz.

Marketplace channel/account bir `Marketplace Clearing Account` ile eşleşir. Marketplace invoice financial effect bu clearing Account'a gider. WooCommerce channel config direct customer Account mapping veya clearing modeli seçebilir. Mars B2B pre-bound Account kullanır.

## 12. Marketplace settlement / payout handoff
Adapter provider'ın sunduğu settlement/accounting/payout evidence'ı normalize eder; **finance posting authority adapter değildir**.

Akış:
`Provider Evidence → Dedupe/Normalize → MarketplaceSettlement source → Finance use-case → AccountTransaction/TreasuryMovement`.

Normalize edilebilen line types örnek:
- sale/invoice receivable reference
- payout
- commission
- service fee
- shipping fee
- penalty/adjustment
- refund/chargeback
- withholding/other provider deduction where legitimate and evidenced

Aynı provider settlement row external identity ikinci finance effect üretmez. Clearing reconciliation owner kuralları `11_FINANS_SATINALMA_IADE.md` ve `06_IS_KURALLARI_VE_INVARIANTLAR.md`dedir.

## 13. Kanal stok yayını
`publish_qty = physical - reserved - quarantine/blocked - channel_safety_stock`.

Kanal bazında:
- stock source warehouse
- safety stock
- publish on/off
- provider max/min publish policy where needed
- last desired state / last acknowledged state
ayarlanabilir.

Stock publish **CURRENT_DESIRED_STATE** semantiğindedir. Eski retry yeni stok değerini geriye götüremez.

## 14. Ürün / kanal mapping
Mars authority mapping unit'i **Product/SKU**'dur. External provider listing/variant identity Product/SKU'ya bağlanır.

Provider parent/child veya variant group isterse external grouping metadata mapping'de tutulabilir. Mars'ta gerçek family/variant grouping ihtiyacı doğarsa `05` ve `27`de tanımlanan additive `ProductFamily/VariantRelation` seam'i kullanılır.

Family/grouping hiçbir zaman stock/price/cost authority olmaz; bu authority Product/SKU seviyesinde kalır.

Mapping alanları provider'a göre category/attribute/brand/external listing identity taşıyabilir. Provider katalog eşleşmesi onay/red veya asynchronous task kullanıyorsa process state ayrı tutulur.

## 15. Kanal görselleri / media publish
Product image destination provider/account bazlı main/gallery/order taşır.

Adapter capability'sine göre:
- main image
- gallery images
- image order
- min/max dimensions
- format/size validation
- provider image URL/upload model
- publish/status/result
normalize edilir.

Provider image capability sunmuyorsa `Görsel` tabı upload yapıyormuş gibi no-op olmaz; manual/unsupported gösterir. Media publish stock/price authority değildir.

## 16. Fiyat
Core ürün tek **net** satış fiyatı taşır. Kanal seviyesinde explicit adjustment/commission-aware export rule olabilir fakat generic çoklu price-list motoruna dönüşmez. Provider'a publish edilen gross/consumer price KDV/tax contract'a göre deterministik türetilir.

B2B fiyatı:
`Ürün Satış Fiyatı - Cari İskontosu`.

Marketplace komisyonu satış fiyatı authority değildir; gerçek provider evidence varsa profitability/settlement source olur.

## 17. Kanal özel kapsam notları
### Hepsiburada
Target: katalog/listeleme, media where available, stok/fiyat, sipariş, sevkiyat, talep/iade, satıcı soruları ve provider'ın sunduğu accounting/settlement evidence.

### Amazon SP-API
Target: Catalog/Product Type Definitions/Listings, inventory/price, Orders, shipment confirmation, Reports/settlement evidence ve provider'ın desteklediği refund/return operasyonları. FBA/FBM farkları capability/policy ile ayrılır.

### n11
Target: ürün/listing, media where available, stok/fiyat, sipariş, sevkiyat, iade ve ürün soru-cevap.

### PttAVM
Target: ürün/listing, stok/fiyat, sipariş ve kargo/fatura/iade gibi güncel API capability'leri. Legacy SOAP gerekirse adapter arkasında izole edilir.

### idefix
Target: ürün/kategori/özellik/marka, stok/fiyat, sipariş/sevkiyat, fatura linki, iade, ürün ve sipariş soruları.

### Allesgo
Target: provider API'sinin sunduğu ürün/listing, stok/fiyat, sipariş, sevkiyat, iade, soru/cevap ve payment/settlement evidence operasyonları.

## 18. Deferred Marketplace Candidates
Çiçeksepeti, Pazarama, Koçtaş, Teknosa, Temu Türkiye ve Boyner için yalnız aday kaydı tutulur.

Adapter açma önkoşulu:
1. güncel resmî API dokümanı **veya** gerçek seller/partner portal erişimi,
2. authentication modeli,
3. en az ürün/listing + stok/fiyat + sipariş capability'lerinin doğrulanması,
4. rate-limit/pagination/status davranışının belgelenmesi,
5. gerçek endpoint contract fixture'larının hazırlanabilmesi.

Bu koşullar sağlanmadan tahmine dayalı endpoint/credential kodu yazılmaz.

## 19. B2B cari bağlantısı
B2BUser önceden bir Mars Account'a bağlıdır:
`Cari → B2B Kullanıcı → B2B Sipariş → Mars Satış Siparişi`.

Siparişte cari seçilmez. B2B order cari bakiyesini etkilemez; invoice etkiler.

## 20. B2B authentication / izinler
B2B internal Mars User değildir. Ayrı auth context/guard kullanır.

Auth lifecycle:
- activation/deactivation
- password set/reset
- login/logout
- session/token revoke
- rate-limit/security audit

Permission örnekleri:
- sipariş
- fiyat görme
- stok görme
- bakiye
- cari ekstre
- fatura
- sipariş geçmişi
- adres yönetimi

Cari `B2B / Bayi Erişimi` alanları:
- active
- kullanıcı/roller/izinler
- default warehouse
- risk davranışı
- adres yetkisi
- stok/fiyat görünürlüğü

Order submit server-side risk/exposure ve stock policy'den geçer.

## 21. API
External API `/api/v1` altında versionlanır.

- session/token/client auth modele göre
- company/client scope
- Resource/DTO allow-list
- validation/authorization
- rate limit
- write idempotency
- normalized request fingerprint

Same idempotency key farklı payload ile conflict'tir. Eloquent public API contract değildir.

## 22. Webhook / polling
Webhook destekleyen provider'da signature/HMAC where available, timestamp/replay defense, atomic Inbox, provider-account scoped dedupe, redacted evidence ve audited replay uygulanır.

Webhook sunmayan veya eksik event sunan provider cursor/watermark polling kullanır. Polling aynı Inbox/dedupe yoluna girer; ikinci business engine oluşturmaz.

## 23. E-Belge
Mars invoice lifecycle ile provider e-document lifecycle ayrıdır. Provider-neutral adapter:
- submit
- query/status
- XML/PDF/artifact reference
- cancel/response where supported

Marketplace adapterı e-document authority değildir. Marketplace'e fatura linki/dosyası göndermek e-document provider lifecycle'ının yerine geçmez.

## 24. İletişim provider modeli
Kanallar:
- SMS
- E-Posta
- WhatsApp

Provider-specific payload domain içine yayılmaz.

### SMS
Netgsm primary ve İleti Merkezi fallback adaylarıdır; gerçek production sırası credential/test ile kilitlenir.

### E-Posta
SMTP/Lark-compatible SMTP, Resend, Brevo, SendGrid, Mailgun adapterları desteklenebilir.

### WhatsApp
Meta WhatsApp Cloud API veya seçilen provider adapterı.

## 25. Delivery modeli
`Notification → Delivery → ProviderAttempt`.

Queue/outbox üzerinden gönderim, retry/backoff ve kalıcı hata kaydı vardır. Business transaction dış servisin cevabını beklemez.

## 26. Template
Versioned şablon değişken whitelist, preview, test gönderimi, kanal content ve history/version destekler.

## 27. Scanner Agent
Yerel Mars Scanner Agent browser ile WIA/TWAIN veya işletim sistemi scanner API'si arasında localhost köprüsüdür. Çek/senet/belge taramada kullanılır. Business authority değildir.

## 28. Sistem entegrasyonlarının UI yeri
`Ayarlar → Entegrasyonlar`:
- E-Belge
- SMS
- WhatsApp
- E-Posta
- Scanner Agent
- E-Ticaret kanal ayarlarına yönlendirme

Kanal credentials burada duplicate edilmez.

## 29. Search
B2B/e-ticaret ürün araması ilk sürümde PostgreSQL FTS + `pg_trgm` ile başlar. Search fiyat/stok authority değildir.

## 30. Bank scope
Canlı banka API/open-banking V1 kapsamında yoktur. Statement import/reconciliation Treasury/Finance Core'dadır.

## 31. Provider değişiklik yönetimi
Marketplace API'leri version/deprecation açısından dış bağımlılıktır.

Her aktif adapter provider registry + versioned contract fixture/sample payload tests taşır. Provider değişikliği core Sales/Stock/Invoice/Finance modelini değiştirmek zorunda bırakmamalıdır.

## 32. Future provider families — V1 dışı
`27_GELECEK_GENISLEME_ALTYAPISI.md` ileride şu provider family'leri tanımlar:
- shipping
- payment
- accounting_export
- feed_discovery
- exchange_rate
- OCR/document extraction
- AI assistant
- storage

Bunlar marketplace adapter interface'ine zorlanmaz. Registry/credential/idempotency/retry gibi cross-cutting kurallar ortak, business contract family-specific olur.
