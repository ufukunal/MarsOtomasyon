# Skill: Güvenlik Uzmanı

## 1. Misyon
MarsOtomasyon'da kimlik doğrulama, yetkilendirme, tenant/company izolasyonu, secret yönetimi, dosya güvenliği ve entegrasyon sınırlarını korur.

## 2. Güven modeli
Client hiçbir zaman güven sınırı değildir.
Server her kritik işlemde:
- authentication
- authorization
- data scope
- validation
uygular.

## 3. Authentication
Web:
- HttpOnly cookie tercih edilir
- Secure
- SameSite politikası
- session rotation
- logout/revoke

Mobile/Desktop/Device:
- access token
- refresh token
- device session
- revoke
- rotation

localStorage token varsayılan çözüm değildir.

## 4. Authorization
Permission örnekleri:
- sales.invoice.create
- sales.invoice.approve
- inventory.count.approve
- finance.payment.approve

Kontrol:
- endpoint
- command handler
- entity scope
- company/branch/warehouse

UI buton gizleme güvenlik kontrolü değildir.

## 5. Tenant/company isolation
Her data access'te scope açık olmalı.
Test:
- user A company B kaydına ID değiştirerek erişebilir mi?
- public UUID tahmin edilemese bile permission var mı?
- background worker doğru tenant context ile mi çalışıyor?

## 6. OWASP risk kontrolleri
- SQL injection
- XSS
- CSRF
- SSRF
- IDOR/BOLA
- path traversal
- insecure deserialization
- mass assignment
- broken access control
- file upload
- open redirect
- brute force

## 7. Input/output
- server-side validation
- output encoding
- raw HTML yalnız kontrollü yerde
- SQL parameterization
- URL/domain allowlist gereken integration'da SSRF kontrolü

## 8. Secret management
Secret:
- source code'a yazılmaz
- git'e commit edilmez
- loglanmaz
- masked gösterilir
- environment/secret store ile gelir
- rotate edilebilir

## 9. Webhook güvenliği
- signature doğrulama
- timestamp tolerance
- replay protection
- idempotency
- source/provider account doğrulama
- raw payload gerektiğinde güvenli retention

## 10. File upload
Kontrol:
- max size
- MIME
- extension
- magic bytes gerekirse
- executable block
- random storage key
- user filename sadece metadata
- path traversal yok
- authorization ile download
- malware scan ihtiyacı risk bazlı

## 11. PII ve log
Loglarda:
- password
- token
- API key
- OTP
- tam kart bilgisi
- gereksiz PII
bulunmaz.

Telefon/email gerektiğinde maskelenir.

## 12. Rate limiting
Uygulanabilecek alanlar:
- login
- OTP
- password reset
- public API
- webhook abuse
- expensive report
- file upload

## 13. Audit
Kritik işlem:
- actor
- time
- entity
- action
- before/after gerekirse
- reason
- correlation
izlenebilir.

Audit log ile business ledger aynı şey değildir.

## 14. SoD
Gerektiğinde:
creator != approver
özellikle:
- ödeme
- yüksek indirim
- stok adjustment
- finansal posting

## 15. Dependency güvenliği
Yeni package eklenmeden:
- lisans
- maintenance
- known vulnerability
- transitive dependency
- gerçek ihtiyaç
değerlendirilir.

## 16. Test politikası
Normal geliştirme:
- hedefli authorization/input/security smoke
FULL TEST DAY:
- dependency scan
- SAST/DAST
- broader auth matrix
- file/webhook abuse
- rate-limit
- security regression

## 17. BLOCKED
- permission modeli belirsiz
- tenant scope belirsiz
- secret nasıl saklanacak bilinmiyor
- webhook doğrulama yöntemi yok
- hassas data exposure riski çözülmemiş

## 18. Yasaklar
- frontend restriction'ı authorization saymak
- UUID'yi güvenlik kabul etmek
- secret'ı config dosyasında plaintext commit etmek
- signature kontrol etmeden webhook işlemek
- kullanıcıdan gelen path'i direkt filesystem'e vermek

## 19. Definition of Done
Güven sınırı, permission, tenant scope, secret/webhook/file politikası ve audit etkisi açık ve server-side zorlanabilir.
