# MarsOtomasyon AI Skill Router

## Amaç
Her görev başlamadan önce hangi uzmanlıkların ana karar verici, hangi uzmanlıkların reviewer olacağını belirlemek.

## Zorunlu başlangıç çıktısı

```
ACTIVE SKILLS
Primary:
- ...

Reviewers:
- ...

Task:
- ...

Sources checked:
- ...
```

## Genel aktivasyon kuralları

### Mimari / Foundation / API
Primary:
- software-architect
- software-developer
Reviewers:
- database-architect
- security-specialist
- system-devops-specialist

### Veritabanı / normalizasyon / migration
Primary:
- database-architect
- erp-domain-specialist
Reviewers:
- software-architect
- software-developer
- accounting-finance-specialist

### Cari / satış / satınalma / ticari belgeler
Primary:
- erp-domain-specialist
- accounting-finance-specialist
Reviewers:
- database-architect
- software-architect
- software-developer
- software-test-engineer

### Stok / depo / sevkiyat / sayım
Primary:
- warehouse-operations-shipping-specialist
- erp-domain-specialist
Reviewers:
- database-architect
- accounting-finance-specialist
- software-developer
- software-test-engineer

### Üretim / MRP / kapasite / fason
Primary:
- production-planning-specialist
- erp-domain-specialist
Reviewers:
- warehouse-operations-shipping-specialist
- database-architect
- accounting-finance-specialist
- software-architect

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

### Finans / kasa / banka / çek-senet
Primary:
- accounting-finance-specialist
- erp-domain-specialist
Reviewers:
- database-architect
- security-specialist
- software-test-engineer

### UI / Web / Dashboard
Primary:
- ux-ui-specialist
- web-design-specialist
Reviewers:
- graphic-design-specialist
- software-developer
- statistics-analysis-specialist

### Rapor / KPI / analiz
Primary:
- statistics-analysis-specialist
- mba-business-manager
Reviewers:
- accounting-finance-specialist
- erp-domain-specialist
- web-design-specialist

### Güvenlik / auth / permission / webhook / secret
Primary:
- security-specialist
- software-architect
Reviewers:
- software-developer
- system-devops-specialist
- database-architect

### Deployment / backup / restore / update
Primary:
- system-devops-specialist
- software-architect
Reviewers:
- security-specialist
- database-architect
- software-developer

### Bug fix
Primary:
- software-developer
- software-test-engineer
Reviewers:
- bug'ın ait olduğu domain skill'i
- gerekiyorsa security/database/architect

## Her aktif skill için zorunlu dört soru
1. Bu görevde hangi riski kontrol ediyorum?
2. Hangi repo/doküman/kodu doğrulamam gerekiyor?
3. Hangi varsayımı yapmam yasak?
4. DONE için hangi somut kanıt gerekli?

## Çelişki çözüm önceliği
1. Kullanıcının açık ve güncel talimatı
2. Repo içindeki kabul edilmiş proje kararı / ADR
3. İş/domain bütünlüğü
4. Muhasebe ve veri bütünlüğü
5. Güvenlik
6. Operasyonel uygulanabilirlik
7. UX/görsel tercih
8. Model varsayımı

Model varsayımı hiçbir üst maddeyi geçersiz kılamaz.

## Fail-closed
Gerekli iş kuralı veya kaynak bulunamazsa ilgili implementasyon yapılmaz. Durum UNKNOWN/BLOCKED olarak raporlanır.

## Test kuralı
Normal geliştirmede ağır test yok. Ağır senaryolar FULL TEST DAY backlog'una yazılır.

## Git kuralı
Tek geliştirme branch'i `main`dir. AI branch/PR oluşturamaz.
