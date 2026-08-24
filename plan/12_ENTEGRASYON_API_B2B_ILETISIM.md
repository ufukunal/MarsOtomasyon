# 12 — Entegrasyon, API, B2B ve İletişim V4

## 1. Integration Core
WooCommerce, Trendyol ve Mars B2B ayrı business engine değildir. Tek **E-Ticaret Integration Core** üzerine adapter olarak bağlanır.

Mars authority:
- ürün master
- kullanılabilir stok
- temel satış fiyatı
- cari/B2B ticari kuralı
- Mars SalesOrder
- fatura/e-belge referansı

External kanal operasyon/satış kanalıdır; callback Mars truth'unu doğrulamasız overwrite edemez.

## 2. External identity modeli
External entity identity:
`company + provider + internal_channel/account + entity_type + external_entity_id`.

Inbound message identity:
`company + internal_channel/account + provider_message/event_id` veya stabil eşdeğeri.

Bu iki identity ayrıdır. Aynı provider event/order retry duplicate Mars order/stock/finance effect üretemez.

## 3. Kanal Merkezi
V16.3 menüsü:
- Kanal Merkezi
- E-Ticaret Siparişleri
- Ürün Entegrasyonu
- E-Ticaret İadeleri
- Ürün/Sipariş Soruları
- Fatura Entegrasyonu
- Entegrasyon Sorunları
- Kanal Ayarları

## 4. Kanal ayarları
Tabs:
`Bağlantı · Ürün · Sipariş · Fatura · Stok · Görsel`.

WooCommerce:
- Kanal Adı
- Site URL
- Consumer Key
- Consumer Secret
- durum
- bağlantı testi

Trendyol:
- Supplier ID
- API Key
- API Secret
- durum
- bağlantı testi

Mars B2B dahiliyse harici secret istemez.

## 5. Secret UX / güvenlik
- encrypted-at-rest
- save sonrası gerçek secret UI/API read-back yok
- maskeli görünüm
- `Değiştir` ile rotate
- connection test ayrı use-case
- ham HTTP/JSON exception normal kullanıcıya gösterilmez

## 6. Sync ilkeleri
- idempotent import/export
- cursor/page state
- retry/backoff
- provider rate limit
- error/dead center
- external mapping
- conflict policy
- last successful sync / last error visibility
- ambiguous result için blind retry yerine query/reconcile

## 7. E-Ticaret sipariş akışı
`Provider Inbox → Validate/Dedupe → Mars Sales Order → Reservation → Hazırlama/Sevk → Fatura → Kanal güncelleme`.

Kullanıcı-visible kanal durumları:
`Yeni, Hazırlanıyor, Gönderildi, Tamamlandı, İptal, Sorun`.

Mars SalesOrder lifecycle ayrı authority'dir.

## 8. Kanal stok yayını
`publish_qty = physical - reserved - quarantine/blocked - channel_safety_stock`.

Kanal bazında:
- stock source warehouse
- safety stock
- publish on/off
ayarlanabilir.

## 9. Ürün / kanal mapping
Mars product/barcode/variant reference ile external product/listing/variant identity eşleşmesi saklanır. Mars ürün authority'sidir.

## 10. Fiyat
Core ürün tek satış fiyatı taşır. Kanal seviyesinde gerektiğinde explicit adjustment olabilir fakat generic çoklu price-list motoruna dönüşmez.

B2B fiyatı:
`Ürün Satış Fiyatı - Cari İskontosu`.

## 11. B2B cari bağlantısı
B2B user/account önceden bir Mars carisine bağlıdır:
`Cari → B2B Kullanıcı → B2B Sipariş → Mars Satış Siparişi`.

Siparişte cari seçilmez. B2B order cari bakiyesini etkilemez; invoice etkiler.

## 12. B2B izinleri
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

## 13. API
External API `/api/v1` altında versionlanır.

- session/token/client auth modele göre
- company/client scope
- Resource/DTO allow-list
- validation/authorization
- rate limit
- write idempotency
- normalized request fingerprint

Same idempotency key farklı payload ile conflict'tir. Eloquent public API contract değildir.

## 14. Webhook
- signature/HMAC
- timestamp/replay defense
- atomic Inbox
- provider-account scoped dedupe
- safe/redacted raw evidence
- audited replay

## 15. E-Belge
Mars invoice lifecycle ile provider e-document lifecycle ayrıdır. Provider-neutral adapter:
- submit
- query/status
- XML/PDF/artifact reference
- cancel/response where supported

Marketplace adapterı e-document authority değildir.

## 16. İletişim provider modeli
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

## 17. Delivery modeli
`Notification → Delivery → ProviderAttempt`.

Queue/outbox üzerinden gönderim, retry/backoff ve kalıcı hata kaydı vardır. Business transaction dış servisin cevabını beklemez.

## 18. Template
Versioned şablon:
- değişken whitelist
- preview
- test gönderimi
- kanal bazlı content
- history/version

destekler.

## 19. Scanner Agent
Yerel Mars Scanner Agent browser ile WIA/TWAIN veya işletim sistemi scanner API'si arasında localhost köprüsüdür. Çek/senet/belge taramada kullanılır. Business authority değildir.

## 20. Sistem entegrasyonlarının UI yeri
`Ayarlar → Entegrasyonlar`:
- E-Belge
- SMS
- WhatsApp
- E-Posta
- Scanner Agent
- E-Ticaret kanal ayarlarına yönlendirme

Kanal credentials burada duplicate edilmez.

## 21. Search
B2B/e-ticaret ürün araması ilk sürümde PostgreSQL FTS + `pg_trgm` ile başlar. Search fiyat/stok authority değildir.

## 22. Bank scope
Canlı banka API/open-banking V1 kapsamında yoktur. Statement import/reconciliation Treasury/Finance Core'dadır.
