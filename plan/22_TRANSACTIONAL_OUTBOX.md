# 22 — Transactional Outbox Standardı V4.2

## 1. Kural
Business data/ledger + Outbox event aynı PostgreSQL transaction içinde commit edilir. External HTTP/provider side-effect commit sonrası worker'da yapılır.

Stok/cari/treasury ledger gibi aynı DB transaction'da atomik olması gereken core business effect Outbox'a ertelenmez.

## 2. Outbox alanları
En az:
- id
- company_id
- event_id unique
- event_type
- schema_version
- aggregate/source type + id
- source/version/sequence when ordering matters
- payload/reference
- correlation_id
- semantic_class
- retry_capability
- status
- attempts
- available_at
- claimed/lease timestamps
- processed_at
- last_error metadata
- created_at/updated_at

Secret plaintext payload'a yazılmaz.

## 3. Internal event catalog
`event_type` rastgele string değildir. Event publish edilmeden önce code/repository catalog'unda:
- canonical name
- owner module
- schema version
- semantic class
- payload allow-list/DTO
- consumer compatibility note
- retention/sensitivity classification where relevant
bulunur.

Örnek naming:
- `sales.invoice.posted.v1`
- `inventory.stock.changed.v1`
- `treasury.movement.posted.v1`
- `commerce.stock.publish_requested.v1`
- `returns.received.v1`

Catalog fiziksel DB tablosu olmak zorunda değildir; code registry + tests yeterli olabilir.

Bu yaklaşım **Event Sourcing değildir**. Event replay business ledger'ı yeniden yaratmanın varsayılan yolu değildir; authority source tablolar/ledger'lardır.

## 4. Event contract compatibility
`event_type + schema_version` contract'tır. Breaking change yeni version kullanır. Queue'da kalan unsupported version silent ack/drop edilmez; backlog drain/event migration/manual review gerekir.

Consumer current code'da eski event'i current master değerleriyle sessizce yeniden yorumlayamaz.

## 5. Delivery semantic class
Event iki temel davranıştan birini veya domain-equivalent explicit semantiği taşır.

### `IMMUTABLE_EVENT_SNAPSHOT`
Invoice posted, approved quote revision, legal/historical notification gibi eventlerde worker **event anındaki immutable snapshot/version** ile çalışır. Current master/state okuyup geçmiş payload'ı sessizce değiştiremez.

### `CURRENT_DESIRED_STATE`
Marketplace stock/price/media publish gibi state-sync işleminde worker current desired state'i yeniden resolve edebilir. Eski retry provider state'ini geriye götüremez.

Consumer hangi semantiği kullanacağını tahmin etmez; event type explicit tanımlar.

## 6. Consumer idempotency
At-least-once normaldir. Consumer event/provider/business identity ile idempotent olmalıdır. Queue/request retry source-effect uniqueness'i bypass edemez.

## 7. Claim / lease
Worker pending mesajları atomic/bounded lease ile claim eder. Stale lease recover edilebilir. İki worker aynı mesajı işlese bile idempotent consumer duplicate business effect üretmez.

## 8. Retry capability
Her external operation capability sınıfı taşır:
- `SAFE_RETRY`
- `IDEMPOTENT_WITH_KEY`
- `QUERY_BEFORE_RETRY`
- `NEVER_AUTO_RETRY`

Network error otomatik olarak güvenli retry anlamına gelmez.

Permanent validation/auth hatası terminal/manual olabilir. Outbox failure business transaction'ı otomatik reversal etmez; compensation explicit business rule ise ayrı use-case'tir.

## 9. Ambiguous provider outcome
Provider işlemi başarılı olup response kaybolabilir.

Bu durumda:
- aynı stable provider reference/idempotency key kullan
- provider query/status reconcile et
- blind duplicate submit yapma

Özellikle payment/e-document/order/shipping gibi non-trivial external operations için zorunludur.

## 10. Ordering / stale update
Delta/financial eventlerde domain-specific strict ordering gerekebilir. Desired-state sync'te current version/state kullanılır. Stale update provider state'ini regress edemez.

## 11. Payload security / minimization
Payload mümkün olduğunca:
- public-safe entity ID
- event/schema version
- minimum immutable snapshot/reference
- correlation metadata
ile sınırlı tutulur.

Token/password/private key yazılmaz. Hassas snapshot gerçekten gerekiyorsa allow-list + encryption/retention policy uygulanır.

## 12. Company lifecycle gate
Worker send öncesi company state'i tekrar kontrol eder. Suspended company outbound default paused/durable'dır. Archived company normal resend yapmaz.

Resume idempotency/staleness/retry-capability kurallarına tabidir.

## 13. Kullanım alanları
- marketplace stock/price/media/order sync
- future shipping/payment/feed providers
- outbound webhook delivery
- SMS/E-Mail/WhatsApp
- e-document provider
- projection/read model async update
- ağır report/export generation trigger
- future OCR/AI processing trigger; AI sonucu business authority değildir

## 14. Observability
Sistem Sağlığı en az:
- pending count
- oldest pending age
- stale lease
- failed/ambiguous
- retries
- provider/type
- semantic class
- stale drop count
- unknown schema version
- manual-review / NEVER_AUTO_RETRY count
izleyebilir.

## 15. Replay
Admin replay permission + audit ile yapılır. Replay consumer idempotency'yi bypass etmez.

AI/OCR suggestion event replay'i accepted business action'ı tekrar post etmez; accepted domain action kendi idempotency identity'sine sahiptir.

## 16. Disaster restore
Recovery barrier açılmadan Outbox resume edilmez. Restore cutoff sonrası provider-success candidate'lar reconcile edilir. Desired-state sync gerekli kanallarda current Mars truth yeniden yayınlanabilir.

## 17. Cleanup / retention
Processed Outbox retention operational/forensic policy'dir; business audit Outbox'a bağlı değildir. Cleanup source-effect/external uniqueness defense'ini ortadan kaldırmaz.
