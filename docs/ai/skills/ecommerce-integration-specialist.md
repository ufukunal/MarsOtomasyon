# Skill: E-Ticaret ve Entegrasyon Uzmanı

## 1. Misyon
B2B, Mimar Paneli, WooCommerce ve pazaryerlerini Mars Commerce Core'a provider bağımlılığını yaymadan, idempotent ve izlenebilir biçimde bağlar.

## 2. Hedef kanallar
- B2B Portal
- Architect Portal
- WooCommerce
- Trendyol
- Hepsiburada
- N11
- ÇiçekSepeti
- Idefix
- gelecekte eklenecek yeni providerlar

## 3. Temel ilke
Mars Product / Variant / Sales Order sistemin merkezidir.
Dış platform kaydı Mars kaydının alternatifi değildir; mapping/listing katmanıdır.

## 4. Provider capability matrisi
Her provider için ayrı doğrulanır:
- product create/update
- variant
- category
- attribute
- image
- price
- stock
- order pull
- webhook
- shipment
- tracking
- invoice
- cancellation
- return
- questions/messages
- campaigns
- rate limit
- bulk API
- pagination
- sandbox

Bir provider'da olan özelliğin diğerinde olduğu varsayılmaz.

## 5. Catalog mapping
Minimum kavramlar:
- channel
- account/store
- product mapping
- variant mapping
- category mapping
- attribute mapping
- external product id
- external variant id
- external SKU
- listing status
- last sync state

## 6. Channel content
Ana ürün Mars'ta kalır.
Kanal bazlı override gerekebilir:
- title
- description
- images
- category
- attributes
- SEO/content
- shipping template

Override yoksa inherit politikası açık olmalıdır.

## 7. Fiyatlama
Channel price hesabında değerlendir:
- base price
- price list
- customer/channel group
- commission
- shipping
- campaign
- tax
- minimum margin
- rounding
- currency
- scheduled price

Provider'a gönderilen fiyatın kaynağı izlenebilir olmalıdır.

## 8. Stok senkronizasyonu
Provider'a gönderilen quantity doğrudan physical on-hand olmak zorunda değildir.
Hesap:
- on-hand
- reservation
- safety stock
- channel allocation
- max sellable
- selected warehouse
- quarantine/hold
- synchronization lag

Negative/oversell politikası açık olmalı.

## 9. Order ingest
Akış:
provider/webhook/poll
-> raw external payload/reference
-> normalized external order
-> mapping/validation
-> Mars Sales Order

Unique:
channel/account + external_order_id

Aynı order tekrar gelirse ikinci Mars order yaratılmaz.

## 10. Webhook + reconciliation
Sadece webhook yeterli sayılmaz.
- webhook hızlı event
- scheduled polling/reconciliation kaçan eventleri bulur
- sync cursor/checkpoint tutulur
- provider updated_at kullanımı incelenir
- duplicate event idempotent işlenir

## 11. Retry
Retry classification:
- timeout -> retry olabilir
- 429 -> Retry-After/backoff
- 5xx -> retry
- invalid SKU -> retry yok, business error
- invalid auth -> retry yok/credential alert

Kör retry yasaktır.

## 12. Outbox
Mars'tan provider'a:
- price update
- stock update
- product update
- shipment
gibi side-effect'ler outbox/worker ile yapılır.

UI, "DB kaydı oluştu" ile "provider'a ulaştı" durumunu ayırır.

## 13. Status normalization
Provider statusları Mars standardına normalize edilir.
Raw status ayrıca saklanabilir.
Mapping tablosu/adapter açık olmalı.

## 14. Returns/cancellations
- external return id
- order line mapping
- received/not received
- financial credit
- stock disposition
- provider refund state
ayrı takip edilir.

## 15. B2B
B2B provider değildir; Mars'ın kendi portalıdır.
Destek:
- company users
- role/permission
- customer-specific price list
- limit/term
- quote/order
- statement/invoice
- payment
- notification

## 16. Architect Portal
Ayrı persona:
- architect profile
- project
- room/space
- product selection
- alternatives
- technical files
- quote request
- sample request
- project attribution

Architect/project source, son Mars order/invoice'a kadar korunabilir.

## 17. Observability
Her sync için:
- channel
- entity
- direction
- attempt
- provider request id
- external id
- status
- error code
- started/finished
- retry count
- payload hash/redacted metadata
izlenmelidir.

## 18. Security
- credentials secret store
- webhook signature
- replay protection
- PII masking
- least privilege API key
- rate limit

## 19. Anti-patternler
- provider başına ayrı stok motoru
- provider başına ayrı muhasebe motoru
- external ID'yi internal PK yapmak
- timeout sonrası ikinci order yaratmak
- provider API'sini doğrulamadan method uydurmak
- UI request içinde uzun provider sync

## 20. Definition of Done
Capability, mapping, idempotency, sync direction, retry/reconciliation, status normalization ve Mars document etkisi tanımlı ve izlenebilir.
