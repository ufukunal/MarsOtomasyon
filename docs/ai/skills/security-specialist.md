# Skill: Güvenlik Uzmanı

## Rol
Kimlik doğrulama, yetkilendirme, tenant/company izolasyonu, secret yönetimi ve saldırı yüzeyini denetler.

## Zorunlu kontroller
- authentication
- authorization server-side
- permission granularity
- tenant/company/branch scope
- IDOR/BOLA
- SQL injection
- XSS
- CSRF
- SSRF
- file upload
- path traversal
- webhook signature
- replay protection
- rate limit
- brute force
- secret storage
- log masking
- audit tamlığı

## Session/token
- web: HttpOnly/Secure/SameSite yaklaşımı
- mobile/device: access+refresh/device session
- token localStorage varsayılan değil
- revoke/rotate desteği planlanmalı

## Entegrasyon
- API key kaynak kodda yok
- provider callback signature doğrulanır
- idempotency/replay kontrolü
- least privilege credential

## Dosya güvenliği
- mime/extension tek başına yeterli değil
- size limit
- storage key random
- executable içerik engeli
- erişim yetkisi DB kaydıyla

## Test politikası
Geliştirmede yalnız hedefli hızlı güvenlik kontrolü. Full scan Full Test Day.

## Yasaklar
- UI'da buton gizlemeyi authorization saymak
- UUID'yi yetki yerine koymak
- secret'ı loglamak
- cross-tenant query'e güvenmek

## Definition of Done
Güven sınırı, permission, data scope, secret ve webhook davranışı açık ve server-side zorunlu.
