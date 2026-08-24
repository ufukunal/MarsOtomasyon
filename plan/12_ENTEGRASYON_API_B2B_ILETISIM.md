# 12 — Entegrasyon, API, B2B ve İletişim V4

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

Amazon için ilk operasyon odağı Türkiye marketplace'idir; adapter marketplace/region kimliğini modelde ayrı taşıyarak SP-API'nin bölgesel yapısına hazır kalır.

**Deferred / API doğrulama bekleyen marketplace adayları:**
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
- temel satış fiyatı
- cari/B2B ticari kuralı
- Mars SalesOrder
- fatura/e-belge referansı

External kanal operasyon/satış kanalıdır; callback Mars truth'unu doğrulamasız overwrite edemez.

## 2. Adapter contract
Her marketplace adapterı aynı business contract'ın yalnız provider tarafından desteklenen capability'lerini uygular.

Ortak capability başlıkları:
- connection/authentication
- category/attribute/brand/reference-data discovery
- product/listing create/update/status
- product/listing mapping
- stock publish
- price publish
- order import
- order acknowledgement/status
- shipment/package/cargo operations
- cancellation
- return/claim/RMA
- invoice/e-document reference upload/sync where supported
- product/order questions where supported
- marketplace accounting/settlement evidence where supported
- webhook/notification where supported
- polling/cursor fallback where webhook yoksa

Bir provider capability sunmuyorsa adapter `unsupported` döndürür. Core veya UI bunu başarı gibi emüle etmez.

## 3. Capability matrix
Kanal bazlı gerçek özellik desteği `channel_capabilities` veya eşdeğer typed config/read-model ile belirlenir.

Örnek capability flags:
- `catalog_read`
- `product_create`
- `product_update`
- `inventory_write`
- `price_write`
- `orders_read`
- `shipment_write`
- `cancel_write`
- `returns_read_write`
- `invoice_write`
- `questions_read_write`
- `settlement_read`
- `webhook`

Capability provider dokümantasyonuna göre adapter implementasyonunda kilitlenir; kullanıcı ayarı provider'ın desteklemediği özelliği açamaz.

## 4. External identity modeli
External entity identity:
`company + provider + internal_channel/account + entity_type + external_entity_id`.

Inbound message identity:
`company + internal_channel/account + provider_message/event_id` veya stabil eşdeğeri.

Bu iki identity ayrıdır. Aynı provider event/order retry duplicate Mars order/stock/finance effect üretemez.

Provider-specific listing/SKU/order/package/claim IDs ayrı external mapping olarak saklanır; tek bir `external_id` alanına bütün provider kimlikleri sıkıştırılmaz.

## 5. Kanal Merkezi
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

## 6. Kanal ayarları
Tabs:
`Bağlantı · Ürün · Sipariş · Fatura · Stok · Görsel`.

Provider credential formu adapter-owned schema ile oluşturulur fakat secret davranışı ortaktır.

### WooCommerce
- Kanal Adı
- Site URL
- Consumer Key
- Consumer Secret

### Trendyol
- Supplier ID
- API Key
- API Secret

### Hepsiburada
Merchant/integrator account bilgileri ve güncel authentication alanları provider dokümantasyonuna göre adapter tarafından tanımlanır.

### Amazon SP-API
- marketplace/region
- seller/account identity
- application authorization metadata
- LWA/SP-API authorization secrets/tokens provider contract'a göre encrypted storage

### n11
- seller/app identity
- app key
- app secret

### PttAVM
- merchant identity
- API Key/token veya provider'ın yürürlükteki authentication alanları

### idefix
- vendor/seller identity
- API Key
- API Secret

### Allesgo
Seller/account identity ve yürürlükteki API credentials adapter config schema ile tanımlanır.

Mars B2B dahiliyse harici secret istemez.

Credential alan adları provider API değiştikçe adapter config schema ile güncellenir; core tabloda sağlayıcıya özel onlarca kolon açılmaz.

## 7. Secret UX / güvenlik
- encrypted-at-rest
- save sonrası gerçek secret UI/API read-back yok
- maskeli görünüm
- `Değiştir` ile rotate
- connection test ayrı use-case
- ham HTTP/JSON exception normal kullanıcıya gösterilmez
- refresh/access token lifecycle adapter'ın secure credential store'u üzerinden yürür
- provider token/secret Outbox payload'a plaintext yazılmaz

## 8. Sync ilkeleri
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

## 9. E-Ticaret sipariş akışı
`Provider Inbox/Poll → Validate/Dedupe → Mars Sales Order → Reservation → Hazırlama/Sevk → Fatura → Kanal güncelleme`.

Kullanıcı-visible kanal durumları ortak normalizasyonda:
`Yeni, Hazırlanıyor, Gönderildi, Tamamlandı, İptal, Sorun`.

Provider'ın ham status'u ayrıca evidence olarak saklanabilir; Mars SalesOrder lifecycle ayrı authority'dir.

## 10. Kanal stok yayını
`publish_qty = physical - reserved - quarantine/blocked - channel_safety_stock`.

Kanal bazında:
- stock source warehouse
- safety stock
- publish on/off
- provider max/min publish policy where needed
- last desired state / last acknowledged state
ayarlanabilir.

Stock publish **CURRENT_DESIRED_STATE** semantiğindedir. Eski retry yeni stok değerini geriye götüremez.

## 11. Ürün / kanal mapping
Mars product/barcode/variant reference ile external product/listing/variant identity eşleşmesi saklanır. Mars ürün authority'sidir.

Mapping alanları provider'a göre category/attribute/brand/external listing identity taşıyabilir. Provider katalog eşleşmesi onay/red veya asynchronous task kullanıyorsa process state ayrı tutulur.

## 12. Fiyat
Core ürün tek satış fiyatı taşır. Kanal seviyesinde gerektiğinde explicit adjustment/commission-aware export rule olabilir fakat generic çoklu price-list motoruna dönüşmez.

B2B fiyatı:
`Ürün Satış Fiyatı - Cari İskontosu`.

Marketplace komisyonu satış fiyatı authority değildir; raporlama/maliyet katkısı için provider evidence olarak tutulabilir.

## 13. Kanal özel kapsam notları
### Hepsiburada
Target: katalog/listeleme, stok/fiyat, sipariş, sevkiyat, talep/iade, satıcı soruları ve provider'ın sunduğu muhasebe/fatura akışları.

### Amazon SP-API
Target: Catalog/Product Type Definitions/Listings, inventory/price, Orders, shipment confirmation, Reports/settlement evidence ve provider'ın desteklediği refund/return operasyonları. FBA/FBM farkları capability/policy ile ayrılır; biri diğerine varsayılmaz.

### n11
Target: ürün/listing, stok/fiyat, sipariş, sevkiyat, iade ve ürün soru-cevap.

### PttAVM
Target: ürün/listing, stok/fiyat, sipariş ve kargo/fatura/iade gibi provider'ın güncel API'de sunduğu operasyonlar. Legacy SOAP gerekirse adapter arkasında izole edilir; core SOAP bilmez.

### idefix
Target: ürün/kategori/özellik/marka, stok/fiyat, sipariş/sevkiyat, fatura linki, iade, ürün soruları ve sipariş soruları.

### Allesgo
Target: provider'ın API'de sunduğu ürün/listing, stok/fiyat, sipariş, sevkiyat, iade, soru/cevap ve ödeme/settlement evidence operasyonları. Güncel capability provider contract fixture ile doğrulanır.

## 14. Deferred Marketplace Candidates
Çiçeksepeti, Pazarama, Koçtaş, Teknosa, Temu Türkiye ve Boyner için yalnız aday kaydı tutulur.

Adapter açma önkoşulu:
1. güncel resmî API dokümanı **veya** gerçek seller/partner portal erişimi,
2. authentication modeli,
3. en az ürün/listing + stok/fiyat + sipariş capability'lerinin doğrulanması,
4. rate-limit/pagination/status davranışının belgelenmesi,
5. gerçek endpoint contract fixture'larının hazırlanabilmesi.

Bu koşullar sağlanmadan tahmine dayalı endpoint/credential kodu yazılmaz.

## 15. B2B cari bağlantısı
B2B user/account önceden bir Mars carisine bağlıdır:
`Cari → B2B Kullanıcı → B2B Sipariş → Mars Satış Siparişi`.

Siparişte cari seçilmez. B2B order cari bakiyesini etkilemez; invoice etkiler.

## 16. B2B izinleri
Örnek permission'lar:
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

## 17. API
External API `/api/v1` altında versionlanır.

- session/token/client auth modele göre
- company/client scope
- Resource/DTO allow-list
- validation/authorization
- rate limit
- write idempotency
- normalized request fingerprint

Same idempotency key farklı payload ile conflict'tir. Eloquent public API contract değildir.

## 18. Webhook / polling
Webhook destekleyen provider'da:
- signature/HMAC where available
- timestamp/replay defense
- atomic Inbox
- provider-account scoped dedupe
- safe/redacted raw evidence
- audited replay

Webhook sunmayan veya eksik olay sunan provider için cursor/watermark polling kullanılır. Polling de aynı Inbox/dedupe yoluna girer; ikinci business engine oluşturmaz.

## 19. E-Belge
Mars invoice lifecycle ile provider e-document lifecycle ayrıdır. Provider-neutral adapter:
- submit
- query/status
- XML/PDF/artifact reference
- cancel/response where supported

Marketplace adapterı e-document authority değildir. Marketplace'e fatura linki/dosyası göndermek e-document provider lifecycle'ının yerine geçmez.

## 20. İletişim provider modeli
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

## 21. Delivery modeli
`Notification → Delivery → ProviderAttempt`.

Queue/outbox üzerinden gönderim, retry/backoff ve kalıcı hata kaydı vardır. Business transaction dış servisin cevabını beklemez.

## 22. Template
Versioned şablon:
- değişken whitelist
- preview
- test gönderimi
- kanal bazlı content
- history/version

destekler.

## 23. Scanner Agent
Yerel Mars Scanner Agent browser ile WIA/TWAIN veya işletim sistemi scanner API'si arasında localhost köprüsüdür. Çek/senet/belge taramada kullanılır. Business authority değildir.

## 24. Sistem entegrasyonlarının UI yeri
`Ayarlar → Entegrasyonlar`:
- E-Belge
- SMS
- WhatsApp
- E-Posta
- Scanner Agent
- E-Ticaret kanal ayarlarına yönlendirme

Kanal credentials burada duplicate edilmez.

## 25. Search
B2B/e-ticaret ürün araması ilk sürümde PostgreSQL FTS + `pg_trgm` ile başlar. Search fiyat/stok authority değildir.

## 26. Bank scope
Canlı banka API/open-banking V1 kapsamında yoktur. Statement import/reconciliation Treasury/Finance Core'dadır.

## 27. Provider değişiklik yönetimi
Marketplace API'leri version/deprecation açısından dış bağımlılıktır.

Her aktif adapter:
- API version/source documentation reference
- capability set
- last compatibility verification date
- sandbox/test/prod config ayrımı where available
- deprecated endpoint replacement plan
- contract fixture/sample payload tests

taşır.

Provider değişikliği core Sales/Stock/Invoice modelini değiştirmek zorunda bırakmamalıdır.
