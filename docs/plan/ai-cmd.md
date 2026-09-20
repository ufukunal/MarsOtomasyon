# MarsOtomasyon AI Commands

Bu dosya MarsOtomasyon üzerinde çalışan AI için bağlayıcı çalışma protokolüdür.

## 1. Kesin Git kuralı

ONLY BRANCH = `main`

AI:
- feature/fix/chore/dev/staging/release/geçici branch oluşturamaz.
- PR tabanlı çalışma başlatamaz.
- başka branch'e commit hazırlayamaz.
- bütün repo değişikliklerini yalnız `main` hedefiyle yapar.
- GitHub kuralı doğrudan main yazımını engelliyorsa branch/PR ile dolaşmaz; BLOCKED olarak raporlar.

## 2. Kaynak önceliği

1. Mevcut repo kodu
2. Bu dosya
3. docs/ai/skill-router.md ve aktif skill dosyaları
4. İlgili modül planı
5. docs/db/
6. Kabul edilmiş ADR/kararlar
7. Gerçek Git/CI durumu
8. Sohbet hafızası

Sohbet hafızası repo kararının önüne geçmez.

## 3. Kodlamadan önce zorunlu preflight

AI şu bilgileri doğrulamadan implementasyona başlamaz:
- repository
- target branch = main
- HEAD
- görev/kapsam
- ilgili modül belgeleri
- ilgili DB sözleşmesi
- aktif skill seti
- mevcut kod
- yasak/değiştirilmemesi gereken alanlar

AI yalnız "okudum" diyemez; kullandığı kaynakları görev başlangıcında kısa olarak belirtir.

## 4. Skill router zorunluluğu

Her görevde `docs/ai/skill-router.md` çalıştırılır.
ACTIVE SKILLS belirlenmeden:
- mimari karar alınamaz
- DB tasarlanamaz
- kod yazılamaz
- UI tasarlanamaz
- entegrasyon davranışı belirlenemez

## 5. Varsayım yasağı

Gerekli gerçek repo belgelerinde/kodda yoksa:
- tahmin etme
- "muhtemelen" diyerek implement etme
- başka projeden kalıp taşıma

Durum `UNKNOWN` veya `BLOCKED` olur.

## 6. Mimari sabitleri

Değiştirilmedikçe:
- Backend: .NET / ASP.NET Core / C#
- Architecture: modular monolith
- Database: PostgreSQL
- Cache: Valkey
- Background: .NET Worker + transactional outbox
- Realtime: SignalR
- Frontend: HTML + CSS + TypeScript + ES Modules + Vite
- UI: Mars kendi component sistemi
- Web/Desktop/Mobile ortak frontend çekirdeği
- Desktop shell ve Mobile shell platform adapterları
- Docker / Docker Compose
- source-of-truth PostgreSQL

Yeni teknoloji yalnız açık ihtiyaç ve karar ile eklenir.

## 7. Veritabanı kuralları

- Şema değişikliği migration ile.
- Master data normalize.
- OLTP ağırlıklı 3NF.
- Historical snapshot bilinçli denormalizasyon.
- Ledger authoritative.
- Projection/cache yeniden üretilebilir.
- Para/miktar decimal/NUMERIC.
- Stok/bakiye authoritative kolon olarak elle tutulmaz.

## 8. ERP posting kuralları

- Sipariş fiziksel stok çıkışı değildir.
- Sevkiyat fiziksel stok hareketidir.
- Fatura finansal alacak/borç etkisidir.
- Kaynak sevkiyat varsa fatura stoğu ikinci kez düşürmez.
- Tahsilat/ödeme finansal kapamadır.
- Posted ledger kaydı sessiz update/delete edilmez; reversal ilkesi kullanılır.
- Kısmi sevk/fatura/kapama izlenebilir olmalıdır.

## 9. Test politikası

Normal geliştirme sırasında AĞIR TESTLER ÇALIŞTIRILMAZ.

Normal akış:
- compile/build
- değişen alana ait hızlı unit/invariant
- gerekiyorsa küçük smoke
- temel migration/contract kontrolü

Ağır testler ayrı FULL TEST DAY'de:
- full unit/regression
- full PostgreSQL integration
- full browser E2E
- Web/Desktop/Mobile
- B2B/Mimar/Marketplace
- Mail/SMS/WhatsApp/Push
- security
- backup/restore
- concurrency
- performance/load
- accounting/ledger invariants

AI kullanıcı açıkça Full Test Day'e geçmeden ağır suite başlatamaz.

## 10. Scope disiplini

- Kullanıcı istemedikçe kapsam genişletme.
- İlgisiz refactor yapma.
- Aynı iş için ikinci sistem oluşturma.
- Mevcut modülü okumadan yeniden yazma.
- UI sorununu domain kuralı ile çözme.
- Domain kuralını yalnız UI'da uygulama.

## 11. DONE kuralı

"Tamamlandı" ancak gerçek kanıtla söylenir:
- hedef main
- ilgili kod/doküman mevcut
- hızlı kontroller gerekli seviyede geçti
- migration/contract gerekiyorsa mevcut
- scope dışı değişiklik yok
- açık UNKNOWN/BLOCKED yok

Ağır testlerin yapılmamış olması geliştirme sırasında normaldir; bunlar Full Test Day backlog'una taşınır.

## 12. İletişim

AI gerçek durum ile tahmini ayırır.
- SOURCE: repo/kod/DB/CI gerçeği
- INFERENCE: açıkça belirtilmiş çıkarım
- UNKNOWN: kaynak yok
- BLOCKED: ilerlemek için karar/erişim gerekiyor

Kaynak yokken kesin ifade kullanılmaz.
