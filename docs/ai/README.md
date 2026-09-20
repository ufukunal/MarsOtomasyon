# MarsOtomasyon AI Skill System

## Amaç
Bu sistem AI'nın "genel bilgiyle" veya eski sohbet hafızasıyla kafasına göre karar vermesini engellemek için vardır. AI yalnız repo gerçekleri, açık kullanıcı kararları ve aktif skill sözleşmeleri üzerinden çalışır.

## Bağlayıcılık
Bu klasördeki skill dosyaları tavsiye değildir. MarsOtomasyon üzerinde çalışan AI için zorunlu çalışma sözleşmesidir.

## Global çalışma kuralları

### 1. Kaynak olmadan karar yok
AI herhangi bir teknik veya domain kararı vermeden önce ilgili kaynakları okumalıdır.

Kaynak yoksa:
- tahmin etmez
- geçmiş başka projeden doldurmaz
- "muhtemelen" diyerek implement etmez

Durum:
- UNKNOWN
veya
- BLOCKED
olarak raporlanır.

### 2. "Okudum" tek başına geçerli değil
Görev başlangıcında AI en az şu bilgileri açıkça üretir:

```
CONTEXT RECEIPT

Repository:
Branch:
HEAD:
Task:
Module:

Sources checked:
- ...
- ...

ACTIVE SKILLS
Primary:
- ...
Reviewers:
- ...

Known constraints:
- ...

Unknown/Blocked:
- ...
```

Bu kayıt olmadan implementasyona başlanmaz.

### 3. Skill seçimi zorunlu
`skill-router.md` her görevde çalıştırılır.
Tek bir skill bütün kararı sahiplenemez.
Domain, DB, security, UX veya finance etkisi varsa ilgili reviewer aktive edilir.

### 4. Primary / Reviewer ayrımı
Primary skill çözümün ana kararını üretir.
Reviewer skill kendi uzmanlık alanındaki hataları engeller.

Reviewer:
- primary skill'i geçersiz yere yeniden tasarlamaz
- kendi alanındaki ciddi çelişkiyi BLOCKED yapabilir
- varsayım üretmez

### 5. Kaynak önceliği
1. Kullanıcının güncel açık talimatı
2. Mevcut repo kodu ve gerçek Git durumu
3. docs/plan/ai-cmd.md
4. Kabul edilmiş ADR/kararlar
5. İlgili modül planı
6. docs/db/
7. Aktif skill dosyaları
8. CI/test kanıtı
9. Sohbet hafızası
10. Model genel bilgisi

Alt kaynak üst kaynağı sessizce geçersiz kılamaz.

### 6. Gerçek / çıkarım ayrımı
AI kritik kararlarda:
- SOURCE
- INFERENCE
- UNKNOWN
- BLOCKED
ayrımını korur.

INFERENCE implementasyon kuralı olamaz; önce doğrulanmalıdır.

### 7. Kapsam kontrolü
AI görevin dışına çıkamaz.

Yasak:
- ilgisiz refactor
- yeni teknoloji eklemek
- başka modülü "hazır buradayken" değiştirmek
- istenmeyen UI redesign
- gereksiz schema değişikliği
- paralel ikinci mekanizma oluşturmak

### 8. Git
ONLY BRANCH = main

AI:
- branch oluşturamaz
- PR oluşturamaz
- main dışında commit hedefleyemez
- force push yapamaz
- GitHub kuralını dolaşamaz

### 9. Test
Normal geliştirmede ağır test çalıştırılmaz.
Hızlı testler değişen kapsam içindir.
Ağır senaryolar FULL TEST DAY backlog'una eklenir.

### 10. "DONE" kanıt ister
AI "tamamlandı" diyebilmek için:
- gerçek HEAD
- değişen dosyalar
- gerekli hızlı kontrol
- migration/doküman durumu
- açık BLOCKED olmadığını
kanıtlamalıdır.

## Skill dosya standardı
Her skill mümkün olduğunca şu bölümleri içerir:
- Misyon
- Yetki sınırı
- Kapsam
- Zorunlu kaynaklar
- Karar çerçevesi
- Mandatory checklist
- Domain/project rules
- Edge cases
- Anti-patternler
- Yasak varsayımlar
- BLOCKED kriterleri
- Zorunlu çıktı
- Definition of Done

## Skill listesi
- software-architect.md
- software-developer.md
- software-test-engineer.md
- database-architect.md
- erp-domain-specialist.md
- accounting-finance-specialist.md
- graphic-design-specialist.md
- web-design-specialist.md
- ux-ui-specialist.md
- statistics-analysis-specialist.md
- mba-business-manager.md
- warehouse-operations-shipping-specialist.md
- production-planning-specialist.md
- ecommerce-integration-specialist.md
- security-specialist.md
- system-devops-specialist.md

## Son ilke
AI'nın hafızası güven kaynağı değildir.
Repo + structured kararlar + kod + DB + test kanıtı güven kaynağıdır.
