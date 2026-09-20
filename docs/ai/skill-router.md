# MarsOtomasyon AI Skill Router

## 1. Amaç
Her göreve başlamadan önce hangi uzmanlıkların karar verici ve hangi uzmanlıkların reviewer olacağını belirlemek.

## 2. Zorunlu başlangıç formatı

```
ACTIVE SKILLS

Task:
Module:

Primary:
- skill-name: neden aktif

Reviewers:
- skill-name: hangi riski kontrol edecek

Sources checked:
- file/path
- file/path

Forbidden assumptions:
- ...

Unknown/Blocked:
- ...
```

Bu çıktı olmadan kod, DB veya UI değişikliği yapılmaz.

## 3. Aktivasyon matrisi

### Mimari / Foundation / API / shared infrastructure
Primary:
- software-architect
- software-developer
Reviewers:
- database-architect
- security-specialist
- system-devops-specialist

### Veritabanı / migration / normalizasyon / ledger schema
Primary:
- database-architect
- erp-domain-specialist
Reviewers:
- software-architect
- software-developer
- accounting-finance-specialist
- security-specialist gerektiğinde

### Cari / satış / satınalma / belge motoru
Primary:
- erp-domain-specialist
- accounting-finance-specialist
Reviewers:
- database-architect
- software-architect
- software-developer
- software-test-engineer

### Stok / depo / sevkiyat / sayım / transfer
Primary:
- warehouse-operations-shipping-specialist
- erp-domain-specialist
Reviewers:
- database-architect
- accounting-finance-specialist
- software-developer
- software-test-engineer
- ux-ui-specialist depo ekranı varsa

### Üretim / MRP / kapasite / fason
Primary:
- production-planning-specialist
- erp-domain-specialist
Reviewers:
- warehouse-operations-shipping-specialist
- database-architect
- accounting-finance-specialist
- software-architect
- software-test-engineer

### B2B / Mimar Paneli / Marketplace
Primary:
- ecommerce-integration-specialist
- mba-business-manager
Reviewers:
- software-architect
- software-developer
- security-specialist
- statistics-analysis-specialist
- ux-ui-specialist
- accounting-finance-specialist fiyat/ödeme varsa

### Finans / banka / kasa / çek-senet
Primary:
- accounting-finance-specialist
- erp-domain-specialist
Reviewers:
- database-architect
- security-specialist
- software-test-engineer
- software-architect gerektiğinde

### UI / Web / Form / Grid
Primary:
- ux-ui-specialist
- web-design-specialist
Reviewers:
- graphic-design-specialist
- software-developer
- erp-domain-specialist
- statistics-analysis-specialist dashboard ise

### Rapor / KPI / BI
Primary:
- statistics-analysis-specialist
- mba-business-manager
Reviewers:
- accounting-finance-specialist
- erp-domain-specialist
- web-design-specialist
- graphic-design-specialist

### Güvenlik / auth / permission / secret / webhook
Primary:
- security-specialist
- software-architect
Reviewers:
- software-developer
- system-devops-specialist
- database-architect
- ecommerce-integration-specialist webhook/provider ise

### Deployment / backup / restore / update center
Primary:
- system-devops-specialist
- software-architect
Reviewers:
- security-specialist
- database-architect
- software-developer
- software-test-engineer

### Bug fix
Primary:
- software-developer
- software-test-engineer
Reviewers:
- bug'ın ait olduğu domain skill'i
- database/security/architect yalnız gerçekten etkileniyorsa

## 4. Reviewer veto kriteri
Reviewer şu durumlarda BLOCKED diyebilir:
- veri kaybı
- yanlış muhasebe posting
- tenant/security ihlali
- stock double-post
- idempotency eksikliği
- historical snapshot bozulması
- documented requirement ile çelişki

Kozmetik veya kişisel teknik tercih veto nedeni değildir.

## 5. Her aktif skill için zorunlu dört soru
1. Bu görevde hangi riski kontrol ediyorum?
2. Hangi repo/doküman/kod kaynaklarını doğruladım?
3. Hangi varsayımı yapmam yasak?
4. DONE için hangi somut kanıt gerekir?

## 6. Çelişki çözüm önceliği
1. Kullanıcının güncel açık kararı
2. Mevcut repo gerçeği
3. Kabul edilmiş ADR
4. Domain/muhasebe/veri bütünlüğü
5. Güvenlik
6. Operasyonel uygulanabilirlik
7. UX
8. Teknik estetik

## 7. Fail-closed
Gerekli karar yoksa:
- implementasyon yapılmaz
- uygun placeholder gerçek kod gibi sunulmaz
- UNKNOWN/BLOCKED yazılır

## 8. Scope gate
Görev başlamadan:
- allowed module
- expected files
- DB impact
- API impact
- integration impact
belirlenir.

Beklenmeyen başka modül etkilenirse dur ve tekrar değerlendir.

## 9. Test gate
Normal geliştirmede heavy test yok.
Reviewer heavy risk tespit ederse FULL TEST DAY backlog'una ekler.

## 10. Git gate
Target = main olmalıdır.
main dışında branch veya PR üretmek yasaktır.

## 11. Completion / Autocomplete Gate
Her görev sonunda skill-router çalışmasının son aşaması `docs/ai/autocomplete.md` protokolüdür.

Primary skill:
- bir sonraki güvenli hedefi belirler
- mevcut görevin gerçek sonucunu prompta taşır

Reviewer skill'ler:
- sonraki promptun kendi disiplinlerinde yanlış varsayım içermediğini kontrol eder
- açık risk veya blocker varsa promptun sonraki implementasyona atlamasını engeller

Final cevapta ayrıntılı NEXT PROMPT yoksa görev completion gate'i geçmemiş sayılır.
