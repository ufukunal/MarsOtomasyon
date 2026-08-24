# 12 — Entegrasyon, API, B2B ve İletişim

## Integration Core
WooCommerce, Trendyol ve Mars B2B ayrı business engine değildir. Tek Integration Core üzerine adapter olarak bağlanır.

Mars authority:
- ürün master
- stok
- temel fiyat
- iç sipariş/fatura

External sistem kimlikleri mapping tablolarıyla tutulur.

## Kanal Merkezi
V16.3 menüsü:
- Kanal Merkezi
- E-Ticaret Siparişleri
- Ürün Entegrasyonu
- E-Ticaret İadeleri
- Ürün/Sipariş Soruları
- Fatura Entegrasyonu
- Entegrasyon Sorunları
- Kanal Ayarları

## Kanal ayarları
Tabs:
`Bağlantı · Ürün · Sipariş · Fatura · Stok · Görsel`.

WooCommerce: Site URL + Consumer Key/Secret.
Trendyol: Supplier ID + API Key/Secret.
Secret save sonrası maskelenir.

## Sync ilkeleri
- idempotent import/export
- cursor/page state
- retry/backoff
- rate limit
- dead/error center
- external id mapping
- conflict policy
- son başarılı sync ve hata görünürlüğü

## Webhook
Signature doğrulama + replay/idempotency. Raw payload güvenli audit için saklanabilir; secret/PII log politikası uygulanır.

## B2B
B2B user önceden bir Mars carisine bağlıdır. Siparişte cari seçmez.

Cari kartındaki `B2B / Bayi Erişimi`:
- aktif/pasif
- kullanıcı/erişim
- izinler
- cari iskonto
- gerekli sipariş ayarları

B2B discount ayrı fiyat motoru değildir; Cari İskontosu kullanır.

## API
API versioned route namespace kullanır. Auth modele göre session/token. Mutating endpoint'ler authorization, validation ve idempotency ihtiyacını açıkça ele alır.

Public/internal API modelleri Eloquent modelin kontrolsüz serialization'ı değildir; resource/DTO kullanılır.

## İletişim provider modeli
Kanallar:
- SMS
- E-Posta
- WhatsApp

Provider adapterları aynı contract'a bağlanır. Provider-specific payload domain içine yayılmaz.

### E-posta sağlayıcı örnekleri
SMTP/Lark uyumlu SMTP, Mailgun, SendGrid, Resend, Brevo.

### SMS
Netgsm ve ek sağlayıcı adapterları eklenebilir.

### WhatsApp
Meta veya seçilen provider adapterları.

## Delivery
`Notification → Delivery → ProviderAttempt`.

Queue üzerinden gönderim, retry/backoff ve kalıcı hata kaydı. Business transaction dış servisin cevabını beklemez; outbox ile commit sonrası yayınlanır.

## Template
Versioned şablon, değişken whitelist, preview/test gönderimi ve kanal bazlı içerik.

## Sistem entegrasyon ayarları
`Ayarlar → Entegrasyonlar`:
- E-Belge
- SMS
- WhatsApp
- E-Posta
- Scanner Agent
- E-Ticaret kanal ayarlarına yönlendirme
