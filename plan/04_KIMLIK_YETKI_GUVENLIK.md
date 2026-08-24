# 04 — Kimlik, Yetki ve Güvenlik

## Kimlik
Laravel authentication/session altyapısı kullanılır. Kullanıcı hesabı aktif/pasif durumu, son giriş, parola yenileme ve gerektiğinde 2FA desteklenir.

## Yetki modeli
RBAC temelidir:
- User
- Role
- Permission

Yetki yalnız menü gizlemek değildir; server-side action/policy seviyesinde zorunlu kontrol edilir.

## Yetki alanları
En az:
- görüntüle
- oluştur
- düzenle
- kesinleştir/post et
- iptal/reversal
- silinebilir master kaydı sil
- export/print
- hassas ayar/credential yönet

## Firma/şube kapsamı
Kullanıcı erişebildiği firma/şubelerle sınırlıdır. Request'ten gelen company/branch id'ye güvenilmez; authenticated context ile doğrulanır.

## Belge güvenliği
Posted/finalized belge normal edit endpoint'iyle değişmez. Düzeltme reversal/correction business flow üzerinden yapılır.

## CSRF / XSS / SQLi
- state-changing web isteklerinde CSRF
- output escaping varsayılan
- raw HTML kontrollü
- parameterized query/Eloquent
- dynamic sort/filter whitelist

## Rate limit
Login, OTP, public API, webhook ve pahalı arama/export endpoint'lerinde Valkey destekli rate limit.

## Secret yönetimi
API key/secret/token:
- source control'a girmez
- DB'de gerekirse application-level encrypted tutulur
- UI'da save sonrası maskelenir
- log/audit payload'a plaintext yazılmaz
- erişim ayrı permission ister

## Dosya upload
- MIME/extension/size doğrulama
- rastgele storage adı
- executable upload engeli
- image/document işleme sınırları
- private dosyaya authorization olmadan doğrudan URL yok

## Webhook
Provider signature doğrulama, timestamp/replay kontrolü, idempotency key ve raw payload audit gerekir.

## Audit
Aşağıdakiler audit edilir:
- login/security olayları
- permission/role değişiklikleri
- credential ayar değişikliği
- finans/stok posting/reversal
- kritik export/import
- backup/restore

## Log güvenliği
Parola, token, secret, tam kart verisi veya gereksiz kişisel veri loglanmaz.

## Dependency güvenliği
Composer lock tutulur. CI dependency advisory/security scan çalıştırır. Paket güncellemesi test geçmeden merge edilmez.
