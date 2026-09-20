# MarsOtomasyon AI Commands

Bu dosya MarsOtomasyon üzerinde çalışan AI için bağlayıcı ve öncelikli çalışma protokolüdür.

## 1. Kesin Git kuralı
ONLY BRANCH = `main`

AI:
- feature/fix/chore/dev/staging/release/geçici branch oluşturamaz
- PR oluşturamaz
- main dışında commit hedefleyemez
- force push yapamaz
- GitHub kuralını branch/PR ile dolaşamaz

GitHub main yazımını engelliyorsa durum BLOCKED olur.

## 2. Her görevde zorunlu Context Receipt
Implementasyondan önce:

```
CONTEXT RECEIPT
Repository:
Branch:
HEAD:
Task:
Module:

Sources checked:
- ...

ACTIVE SKILLS
Primary:
- ...
Reviewers:
- ...

Scope:
- ...

Forbidden assumptions:
- ...

Unknown/Blocked:
- ...
```

AI yalnız "notları okudum" diyerek devam edemez.

## 3. Kaynak önceliği
1. Kullanıcının güncel açık talimatı
2. Mevcut repo kodu ve gerçek Git durumu
3. Bu dosya
4. Kabul edilmiş ADR/kararlar
5. İlgili modül planı
6. docs/db/
7. docs/ai/skill-router.md ve aktif skill dosyaları
8. CI/test kanıtı
9. Sohbet hafızası
10. Genel model bilgisi

## 4. Skill router zorunlu
Her görevde `docs/ai/skill-router.md` kullanılır.
Aktif skill seti belirlenmeden:
- mimari karar alınamaz
- DB tasarlanamaz
- kod yazılamaz
- UI tasarlanamaz
- entegrasyon davranışı belirlenemez

## 5. Varsayım yasağı
Repo/kod/dokümanda olmayan iş kuralı:
- uydurulmaz
- "genelde böyle olur" diye uygulanmaz
- başka projeden taşınmaz

Durum SOURCE / INFERENCE / UNKNOWN / BLOCKED şeklinde ayrılır.
INFERENCE tek başına implementasyon gerekçesi değildir.

## 6. Mimari sabitleri
Açık karar değişikliği olmadıkça:
- .NET / ASP.NET Core / C#
- modular monolith
- PostgreSQL
- Valkey
- .NET Worker
- transactional outbox
- SignalR
- HTML + CSS + TypeScript + ES Modules + Vite
- Mars kendi UI component sistemi
- ortak Web/Desktop/Mobile frontend çekirdeği
- Docker / Docker Compose
- PostgreSQL source-of-truth

## 7. Veritabanı
- migration dışı schema değişikliği yok
- master data normalize
- OLTP ağırlıklı 3NF
- historical snapshot bilinçli denormalizasyon
- ledger authoritative
- projection/cache yeniden üretilebilir
- money/quantity decimal/NUMERIC
- direct authoritative stock/balance column yok

## 8. ERP posting
- teklif stok/cari etkilemez
- sipariş fiziksel stock out değildir
- sevkiyat physical stock movement
- fatura financial receivable/payable
- source dispatch varsa invoice stock'u tekrar düşürmez
- mal kabul stock in
- purchase invoice goods receipt stoğunu tekrar artırmaz
- collection/payment settlement
- posted ledger silent update/delete olmaz
- reversal kullanılır
- partial quantities izlenir

Bu maddeler ilgili modül planı daha spesifikse onunla birlikte uygulanır.

## 9. Scope
AI:
- kullanıcı istemedikçe kapsam büyütemez
- ilgisiz refactor yapamaz
- yeni dependency ekleyemez
- başka modülü "temizlemek" için değiştiremez
- aynı iş için ikinci sistem yazamaz

Unexpected impact çıkarsa durup yeniden değerlendirilir.

## 10. Test politikası
Geliştirme sırasında ağır testler çalıştırılmaz.

Normal:
- build
- hedefli unit/invariant
- küçük smoke
- migration/contract hızlı check

FULL TEST DAY:
- full unit/regression
- PostgreSQL integration
- browser E2E
- Web/Desktop/Mobile
- B2B/Mimar/Marketplace
- mail/SMS/WhatsApp/push
- security
- backup/restore
- concurrency
- performance/load
- accounting/ledger invariants

AI kullanıcı açıkça Full Test Day'e geçmeden heavy suite başlatamaz.

## 11. Gerçeklik kuralı
AI şunları kanıt olmadan söyleyemez:
- tamamlandı
- test geçti
- main'e yazıldı
- API çalışıyor
- migration uygulandı
- provider entegrasyonu başarılı

Bu ifadeler gerçek repo/tool/test sonucu gerektirir.

## 12. DONE
DONE için:
- target main
- gerçek değişiklik repo'da
- ilgili hızlı doğrulama
- migration/doküman etkisi tamam
- açık BLOCKED yok
- skill reviewer ciddi itiraz bırakmıyor

Heavy test yapılmamışsa "Full Test Day pending" açıkça belirtilir.

## 13. BLOCKED davranışı
BLOCKED olduğunda AI:
- engeli açıklar
- başka yöntem uydurmaz
- mimariyi dolanmaz
- branch/PR açmaz
- kullanıcı kararını beklemesi gerekiyorsa bunu net söyler

## 14. Anti-hallucination kuralı
Var olmayan:
- dosya
- sınıf
- kolon
- endpoint
- provider capability
- test sonucu
- commit
uydurulamaz.

Önce repo/tool ile doğrula.
