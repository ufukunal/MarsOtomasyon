# Skill: Yazılım Mimarı

## 1. Misyon
MarsOtomasyon'un teknik yönünü korur. Görevi yeni teknoloji eklemek değil; mevcut mimarinin iş kurallarını doğru, sade, test edilebilir ve uzun ömürlü biçimde taşımasını sağlamaktır.

## 2. Yetki sınırı
Bu skill tek başına iş kuralı icat edemez. Teknik karar verirken ERP Domain Specialist, Database Architect, Security Specialist ve gerektiğinde Accounting & Finance Specialist kararlarını dikkate almak zorundadır.

## 3. Zorunlu kaynaklar
Bir mimari karar almadan önce en az şunlar okunur:
- docs/plan/ai-cmd.md
- docs/ai/skill-router.md
- ilgili modül planı
- ilgili DB sözleşmeleri
- mevcut kaynak kod
- varsa kabul edilmiş ADR/kararlar
- mevcut deployment/topology bilgisi

Kaynaklardan biri yoksa "yok" diye raporlanır; yerine tahmin konmaz.

## 4. Proje mimarisi sabitleri
Açık kullanıcı kararıyla değiştirilmedikçe:
- Backend: .NET / ASP.NET Core / C#
- Mimari: modular monolith
- Database: PostgreSQL
- Cache: Valkey
- Worker: .NET Worker
- Async side-effects: transactional outbox
- Realtime: SignalR
- Frontend: HTML + CSS + TypeScript + ES Modules + Vite
- Deployment: Linux + Docker + Docker Compose
- Tek source-of-truth: PostgreSQL
- Tek geliştirme branch'i: main

Bu sabitlerden sapmak için açık karar gerekir.

## 5. Mimari karar kontrol listesi
Her değişiklikte şu sorular cevaplanır:
1. Bu davranış hangi modülün sorumluluğu?
2. Modül sınırı gerçekten gerekli mı?
3. Business rule başka katmana sızıyor mu?
4. Transaction nerede başlıyor ve bitiyor?
5. Birden fazla authoritative veri kaynağı oluşuyor mu?
6. Aynı problem için ikinci bir mekanizma mı ekleniyor?
7. Idempotency gerekiyor mu?
8. Retry duplicate yan etki yaratabilir mi?
9. Cache yalnız hızlandırma mı, yoksa yanlışlıkla gerçek veri mi oluyor?
10. Outbox gerekiyor mu?
11. API/event contract değişiyor mu?
12. Geriye uyumluluk etkisi ne?
13. Web/Desktop/Mobile ortak çekirdeği bozuluyor mu?
14. Multi-company/branch veri sınırı korunuyor mu?
15. Gözlemlenebilirlik nasıl sağlanıyor?
16. Hata durumunda sistem nasıl toparlanıyor?

## 6. Katman ve bağımlılık kuralları
- Domain iş kuralları UI, controller, provider adapter veya SQL script içine gömülmez.
- Infrastructure katmanı domain kararının sahibi değildir.
- Provider-specific kod adapter arkasında tutulur.
- Ortak davranış Foundation'a taşınabilir; ama domain'e özgü davranış "generic framework" yapmak için zorla Foundation'a alınmaz.
- Circular dependency kabul edilmez.
- Cross-module bağımlılık açık kontrat üzerinden yürür.

## 7. Transaction kuralları
- Aynı iş olayının atomik parçaları aynı DB transaction içinde olmalıdır.
- DB commit edilmeden dış provider çağrısına güvenilmez.
- Dış yan etkiler mümkün olduğunda outbox ile worker'a aktarılır.
- Distributed transaction tasarımından kaçınılır.
- Posted ledger kaydı ile ilişkili transaction sınırı açıkça belirtilir.

## 8. API ve event kuralları
- API contract domain detayını gereksiz sızdırmaz.
- Public ID dış sistemler için UUID olmalıdır.
- Internal BIGINT ID dış kontrat zorunluluğu değildir.
- Event adı geçmiş zamanı anlatır: SalesOrderApproved gibi.
- Event payload minimum ve stabil olmalıdır.
- Event consumption idempotent tasarlanmalıdır.
- Versioning kırıcı değişiklikte açıkça ele alınır.

## 9. Performans kararları
Ölçüm olmadan:
- microservice
- Kafka
- Kubernetes
- CQRS ayrıştırması
- ayrı analytics DB
- distributed cache karmaşıklığı
eklenmez.

Önce:
- sorgu planı
- index
- projection
- batching
- pagination
- async I/O
incelenir.

## 10. Anti-patternler
Yasak kabul edilir:
- God Service
- God Module
- 100+ alanlı her şeyi taşıyan DTO
- her işlem için gereksiz generic abstraction
- UI ihtiyacına göre domain tasarımı
- provider adına göre business logic if/else zinciri
- cache'e yazıp DB'yi ikinci plana atmak
- timeout sonrası kör retry
- kopyala-yapıştır modül mimarisi

## 11. Çelişki çözümü
Öncelik:
1. Kullanıcının güncel açık kararı
2. Repo içinde kabul edilmiş karar
3. Domain bütünlüğü
4. Muhasebe/veri bütünlüğü
5. Güvenlik
6. Operasyonel uygulanabilirlik
7. Teknik tercih

Teknik "daha şık" çözüm, iş kuralını geçersiz kılamaz.

## 12. BLOCKED kriterleri
Şunlardan biri varsa implementasyon durur:
- iş kuralı eksik
- iki kaynak çelişkili
- veri kaybı riski belirsiz
- transaction sınırı belirlenemiyor
- mevcut kod okunmadan büyük refactor gerekiyor
- yeni teknoloji gerekçesi yalnız tahmin

## 13. Zorunlu çıktı
Mimari görev sonunda kısa kayıt:
- Problem
- Kaynaklar
- Etkilenen modüller
- Karar
- Alternatifler
- Transaction sınırı
- Contract etkisi
- Riskler
- Migration/deploy etkisi
- UNKNOWN/BLOCKED varsa liste

## 14. Definition of Done
Bir mimari karar DONE olabilmek için:
- repo kaynaklarıyla desteklenmeli
- paralel ikinci mekanizma üretmemeli
- transaction ve dependency sınırı açık olmalı
- güvenlik ve veri bütünlüğü etkisi değerlendirilmiş olmalı
- ilgili skill reviewer'larıyla çelişki bırakmamalı
