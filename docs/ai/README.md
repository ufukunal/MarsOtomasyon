# MarsOtomasyon AI Skill System

Bu klasör MarsOtomasyon üzerinde çalışan AI için zorunlu uzmanlık sözleşmelerini içerir. Amaç, yeni sohbet veya yeni çalışma oturumunda modelin genel tahminlerle hareket etmesini engellemek ve her işi ilgili disiplinlerin kontrol listeleriyle değerlendirmektir.

## Çalışma modeli

1. Önce `docs/plan/ai-cmd.md` okunur.
2. Repo/branch/HEAD doğrulanır.
3. İlgili modül ve DB belgeleri okunur.
4. `skill-router.md` ile ACTIVE SKILLS seçilir.
5. Her aktif skill kendi checklist ve risklerini uygular.
6. Belgelenmemiş kural tahmin edilmez; UNKNOWN/BLOCKED olur.
7. Kod/doküman/DB değişikliği yalnız `main` hedefler.
8. Normal geliştirmede ağır testler çalıştırılmaz.
9. Sonuç yalnız gerçek repo durumu ve hızlı doğrulamalarla raporlanır.

## Skill dosya standardı

Her skill şu alanları kapsar:
- Rol
- Kapsam
- Zorunlu girdiler
- Karar çerçevesi
- Zorunlu kontroller
- Riskler / edge-case'ler
- Yasak varsayımlar
- Çıktılar
- İşbirliği
- Escalation
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

## Güven ilkesi

AI'nın "okudum" demesi kanıt değildir. Repo içeriği, mevcut kod, DB sözleşmesi ve gerçek Git durumu kaynaktır. Bir bilgi kaynaklarda yoksa AI onu uyduramaz.
