# 04 — Kimlik, Yetki ve Güvenlik V4

## 1. Kimlik
Laravel authentication/session altyapısı kullanılır. Kullanıcı aktif/pasif durumu, son giriş, parola yenileme ve gerektiğinde 2FA desteklenir.

User birden fazla company'ye erişebiliyorsa her request/job explicit active-company context taşır. Branch access ayrıca scope edilebilir.

## 2. Company isolation
Tenant isolation yalnız UI filtresi değildir. Aşağıdakiler birlikte uygulanır:
- authenticated active-company context
- policy/action scope
- route/model binding defense
- same-company reference guard
- queue/job company context
- token/client company scope
- CrossCompanyLeak testleri

Request'ten gelen `company_id/branch_id` tek başına güvenilir değildir.

## 3. Company lifecycle execution gate
Gerektiğinde company lifecycle:
- `active`: normal business mutation
- `suspended`: normal HTTP/API write, scheduler mutation ve outbound provider delivery default BLOCK; inbound event durable Inbox/quarantine'a alınabilir
- `archived`: read/audit terminal; normal business/provider mutation yok

Worker execution anında state tekrar doğrulanır.

## 4. RBAC / permission
Temel model:
- User
- Role
- Permission

Yetki menü gizlemek değildir; server-side action/policy seviyesinde zorunludur.

Kritik permission örnekleri:
- görüntüle / oluştur / düzenle
- belge kesinleştir/post et
- iptal/reversal/correction
- tahsilat/ödeme
- stok adjustment/sayım tamamlama
- fiyat/risk limiti değişikliği
- çek/senet lifecycle action
- banka mutabakatı
- export/print/import
- credential/integration yönetimi
- kullanıcı/rol/yetki yönetimi
- posting-period override
- backup/restore

Role hiçbir business invariant'ı bypass etmez.

## 5. Approval / four-eyes
Yalnız gerçek kritik ihtiyaçta kullanılır; generic BPM engine kurulmaz. Approval target entity/version/hash'e bağlanabilir; material change eski approval'ı geçersiz kılar.

## 6. Posting period override
Closed/frozen period override ayrı permission + reason + audit ve gerekiyorsa approval ister.

## 7. 2FA
Admin, finans ve entegrasyon yetkili rollerinde 2FA desteklenir; production policy ile zorunlu yapılabilir.

## 8. CSRF / XSS / SQLi
- state-changing web isteklerinde CSRF
- output escaping varsayılan
- raw HTML allow-list/kontrollü
- parameterized query/Eloquent
- dynamic sort/filter/column whitelist

## 9. Rate limit
Login, OTP, public API, webhook, connection-test ve pahalı search/export endpoint'leri Valkey destekli rate-limit kullanır.

## 10. Secret / credential güvenliği
API key/secret/token/password/private key:
- source control'a girmez
- encrypted-at-rest tutulur
- plaintext log/audit yok
- save sonrası gerçek değer read-back edilmez
- UI maskeli gösterir
- rotate/değiştir action ayrı permission ister
- connection-test ayrı use-case/permission olabilir

Key rotation historical ciphertext'i okunamaz hale getirmemelidir; recovery key politikası backup runbook ile uyumludur.

## 11. Audit + sensitive diff
Audit en az:
- company
- actor
- action
- entity
- correlation id
- timestamp
- izin verilen diff

taşır.

Secret raw değeri audit diff'e yazılmaz. Hassas PII policy'ye göre masked/classified/encrypted olabilir. Audit business ledger değildir.

## 12. KVKK / PII
Cari/yetkili iletişim verileri için classification, masking, retention/anonymization/legal-hold politikası uygulanabilir. Kesinleşmiş legal/business history cascade-delete edilmez.

## 13. API security
- versioned API (`/api/v1`)
- scoped auth/company/client permission
- rate limit
- Resource/DTO default-deny allow-list
- mutating endpoint idempotency
- same idempotency key + farklı normalized payload = conflict

Eloquent public contract değildir.

## 14. Webhook / inbound security
- HMAC/signature doğrulama
- timestamp/replay kontrolü
- provider account + message/event identity
- atomic Inbox/dedupe
- redacted logs
- audited replay

## 15. Outbound webhook SSRF
Harici URL çağrısı varsa:
- allowed scheme
- localhost/private/link-local/metadata/internal range deny
- DNS ve redirect revalidation
- timeout/response-size cap
uygulanır.

## 16. XML / UBL parser
E-belge/XML parse işlemlerinde XXE/DTD/network default kapalıdır. Size/depth/entity kaynak limitleri uygulanır.

## 17. B2B security
B2B user/account önceden bir Mars carisine bağlıdır. Başka carinin verisini göremez ve siparişte cari değiştiremez.

Permission örnekleri:
- sipariş
- fiyat
- stok
- bakiye/ekstre
- fatura
- sipariş geçmişi
- adres yönetimi

External B2B/API DTO default-deny allow-list kullanır; internal maliyet/margin/provider secret/admin note/yetkisiz bank-contact verisi dışarı çıkmaz.

## 18. Scanner Agent
Local Scanner Agent yalnız localhost/approved local endpoint üzerinden erişilir. Device identity business permission değildir; browser action yine authenticated user/company permission kontrolünden geçer.

## 19. File upload security
- extension/MIME/content sniff allow-list
- byte/pixel/PDF complexity limitleri
- randomized non-executable storage
- checksum
- private access authorization
- risk bazlı malware scan

Async scan kullanılıyorsa `pending/quarantined` dosya normal preview/download/render kaynağı olamaz. Scan failure fail-open değildir.

## 20. Product image security
Original/derived image metadata ve access scope korunur. Channel/site image destination tenant izolasyonunu bypass edemez.

## 21. Report security
Hazır raporlar allow-listed/parameterized source ve company/user permission ile çalışır. Scheduled report runtime authorization'ı tekrar kontrol eder. Kullanıcı-defined raw SQL/JS/PHP execution yüzeyi yoktur.

## 22. Search security
PostgreSQL FTS/`pg_trgm` company scope'u bypass edemez. Search result ID gerçek entity fetch sırasında permission re-check'ten geçer.

## 23. Dependency güvenliği
- `composer.lock` commit edilir
- CI dependency advisory/security scan çalıştırır
- paket update test geçmeden tamamlanmış sayılmaz

## 24. Recovery Mode
Disaster/restore durumunda Recovery Mode normal browser/API/queue/scheduler/outbound mutation'ı fail-closed bloke eder. Entegrasyon backlog'u idempotency/staleness/reconciliation sonrası kontrollü resume edilir.

## 25. Log güvenliği
Parola, token, secret, tam kart verisi ve gereksiz kişisel veri loglanmaz. Correlation ID ile finans/stok olayının kaynağı izlenebilir.
