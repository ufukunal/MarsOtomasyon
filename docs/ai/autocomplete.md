# MarsOtomasyon AI Autocomplete Protocol

## 1. Amaç
Her tamamlanan AI işleminin sonunda kullanıcının bir sonraki mesajda ne yazması gerektiğini AI'nın kendisinin üretmesini zorunlu kılar.

Bu mekanizmanın amacı:
- sohbet değişse bile bağlam kaybını azaltmak
- kullanıcının yeniden teknik kapsam yazmak zorunda kalmamasını sağlamak
- bir sonraki görevin kapsamını açıkça kilitlemek
- AI'nın sonraki sohbette kafasına göre kapsam değiştirmesini önlemek
- repo, HEAD, skill, kaynak, test ve Git kurallarını bir sonraki prompta taşımak

## 2. Zorunluluk
Her işlem bittiğinde AI cevabının en sonunda mutlaka:

```
NEXT PROMPT — KOPYALA / YAPIŞTIR
...
```

bölümü bulunur.

Bu bölüm:
- isteğe bağlı değildir
- kısa "devam et" cümlesi olamaz
- kullanıcıdan aynı bilgileri tekrar yazmasını bekleyemez
- mevcut görevin gerçek sonucuna göre üretilir
- bir sonraki güvenli işi tarif eder

## 3. Ne zaman üretilir?
Şunların hepsinden sonra üretilir:
- kod değişikliği
- doküman değişikliği
- DB tasarımı
- mimari karar
- analiz
- bug fix
- UI çalışması
- entegrasyon çalışması
- GitHub işlemi
- test/inceleme
- yalnızca read-only kontrol yapılmış olsa bile

Bir görev BLOCKED bittiyse de NEXT PROMPT üretilir; bu durumda prompt yalnız blocker'ı çözmeye odaklanır.

## 4. Prompt üretmeden önce zorunlu gerçeklik kontrolü
NEXT PROMPT yazılmadan önce AI mümkün olan yerlerde doğrular:
- repository
- branch
- current HEAD
- yapılan son işlem
- değişen dosyalar
- mevcut modül
- aktif skill seti
- tamamlanan kabul kriterleri
- kalan işler
- açık UNKNOWN/BLOCKED
- hızlı test durumu
- Full Test Day'e ertelenen testler

Doğrulanmayan bilgi kesin gerçek gibi yazılamaz.

## 5. NEXT PROMPT zorunlu içeriği
Bir sonraki prompt, mümkün olduğunda şu bölümleri içerir:

### A. Repo ve Git durumu
- Repository: ufukunal/MarsOtomasyon
- Target branch: main
- Beklenen/current HEAD
- branch oluşturma yasağı
- PR oluşturma yasağı
- force push yasağı

### B. Önce okunacak kaynaklar
Tam path ile:
- docs/plan/ai-cmd.md
- docs/ai/README.md
- docs/ai/autocomplete.md
- docs/ai/skill-router.md
- ilgili skill dosyaları
- ilgili modül planı
- ilgili DB belgeleri
- değiştirilecek mevcut kod

### C. Context Receipt talimatı
Bir sonraki AI'dan implementasyondan önce:
- Repository
- Branch
- HEAD
- Task
- Module
- Sources checked
- Active Skills
- Scope
- Forbidden assumptions
- Unknown/Blocked
çıktısı istenir.

### D. Tam hedef
"Devam et" yerine somut hedef yazılır.
Örnek:
- satış siparişi formunun etkilerini dokümante et
- inventory ledger şemasını tasarla
- aktif task'ın eksik handler'ını tamamla

### E. İşlem sırası
Bir sonraki görev mümkün olduğunca adım adım yazılır:
1. doğrula
2. oku
3. analiz et
4. gerekiyorsa değiştir
5. hızlı kontrol yap
6. main'e yaz
7. sonucu doğrula
8. yeni NEXT PROMPT üret

### F. Scope
Açıkça yaz:
- hangi modül
- hangi dosyalar/klasörler
- DB etkisi
- API etkisi
- UI etkisi
- entegrasyon etkisi

### G. Yapılmaması gerekenler
Göreve göre açık yasaklar yazılır:
- kapsam genişletme
- ilgisiz refactor
- yeni dependency
- yeni branch
- PR
- ağır test
- belgelenmemiş iş kuralını uydurma
- source-of-truth değiştirme

### H. Aktif skill seti
Primary ve Reviewer skill'ler prompt içinde isimleriyle yazılır.
Bir sonraki AI bunları okumadan işlem yapamaz.

### I. Kabul kriterleri
DONE sayılması için ölçülebilir maddeler yazılır.
Örnek:
- belge etkileri açık
- partial/cancel davranışı tanımlı
- DB constraint'leri belirtilmiş
- hızlı kontrol geçti
- main HEAD doğrulandı

### J. Test politikası
Prompt açıkça belirtir:
- yalnız hızlı/hedefli test
- heavy/full suite yok
- ertelenen ağır testleri Full Test Day backlog'una yaz

### K. Çıktı formatı
Bir sonraki AI'dan şunlar istenir:
- yapılanlar
- değişen dosyalar
- commit/HEAD
- hızlı test sonucu
- UNKNOWN/BLOCKED
- Full Test Day pending maddeleri
- en sonda yeni NEXT PROMPT

## 6. Minimum şablon

```
NEXT PROMPT — KOPYALA / YAPIŞTIR

MarsOtomasyon projesine devam et.

Repository: ufukunal/MarsOtomasyon
Target branch: main
Expected HEAD: <gerçek SHA>

Önce şunları oku ve doğrula:
- docs/plan/ai-cmd.md
- docs/ai/README.md
- docs/ai/autocomplete.md
- docs/ai/skill-router.md
- <ilgili skilller>
- <ilgili modül planı>
- <ilgili DB belgeleri>
- <ilgili mevcut kod>

Implementasyondan önce CONTEXT RECEIPT üret:
- Repository
- Branch
- HEAD
- Task
- Module
- Sources checked
- ACTIVE SKILLS
- Scope
- Forbidden assumptions
- Unknown/Blocked

ACTIVE SKILLS
Primary:
- ...

Reviewers:
- ...

HEDEF:
<tek ve net sonraki görev>

UYGULAMA SIRASI:
1. ...
2. ...
3. ...

SCOPE:
- ...

YASAKLAR:
- main dışında branch oluşturma
- PR oluşturma
- force push
- kapsam dışı refactor
- belgelenmemiş kural uydurma
- ağır test suite çalıştırma
- ...

KABUL KRİTERLERİ:
- ...
- ...

TEST:
Yalnız değişen alan için hızlı kontroller yap.
Heavy/full testleri çalıştırma.
Gerekli ağır senaryoları Full Test Day backlog'una ekle.

BİTİŞTE:
- gerçek repo durumunu doğrula
- current main HEAD'i yaz
- değişen dosyaları yaz
- hızlı test sonucunu yaz
- UNKNOWN/BLOCKED varsa yaz
- Full Test Day pending maddelerini yaz
- ve tekrar ayrıntılı NEXT PROMPT üret.
```

## 7. BLOCKED durumunda autocomplete
Görev blocker ile bittiyse AI sonraki teknik işi uyduramaz.

NEXT PROMPT şuna odaklanır:
- blocker'ın tam tanımı
- hangi karar/veri/erişim eksik
- hangi dosyalar okunmalı
- blocker çözülmeden hangi işlemlerin yapılmaması gerektiği
- kullanıcıdan gerekiyorsa tek net karar

## 8. Sonraki iş belli değilse
AI kafasına göre roadmap üretemez.

Sıra:
1. repo roadmap/backlog/task belgelerine bak
2. belgelenmiş sonraki işi seç
3. birden fazla eşit aday varsa prompt, önce mevcut durumu okuyup adayları kullanıcıya sunmayı ister
4. repo içinde sonraki iş yoksa "yeni özellik yaz" demez

## 9. Prompt kalitesi
NEXT PROMPT:
- bağlamdan bağımsız anlaşılabilir olmalı
- yeni sohbete doğrudan yapıştırılabilmeli
- "öncekine devam et" gibi belirsiz referans kullanmamalı
- dosya yollarını tam vermeli
- teknik kararları yeniden uydurmamalı
- mevcut gerçek HEAD'i mümkünse içermeli
- kullanıcının daha önce verdiği sabit kuralları taşımalı
- gereksiz prose değil, uygulanabilir talimat olmalı

## 10. Anti-patternler
Yasak autocomplete örnekleri:
- "Devam et."
- "Şimdi veritabanına geç."
- "Bir sonraki modülü yap."
- "Testleri çalıştır."
- "Kaldığın yerden devam et."

Bunlar bağlam, scope, skill, kaynak, kabul kriteri ve test politikası taşımadığı için geçersizdir.

## 11. Definition of Done
Bir AI cevabı, işlem tamamlanmış olsa bile geçerli final sayılmaz eğer sonunda:
- ayrıntılı NEXT PROMPT yoksa
- prompt mevcut gerçek durumla çelişiyorsa
- branch/PR yasağını taşımıyorsa
- skill/source/scope/test kurallarını taşımıyorsa


## 12. Role-driven session prompt

The detailed binding session/bootstrap rules are defined in:
- `docs/ai/session-execution-protocol.md`

Every NEXT PROMPT must comply with that file in addition to this protocol.

Additional mandatory requirements:
- every active skill must include its exact responsibility in the next task;
- the prompt must distinguish planning, implementation, verification and deployment scope;
- it must carry forward known/locked decisions without converting UNKNOWN items into assumptions;
- it must name the exact allowed repository paths when the task is narrow;
- it must state whether DB/API/UI/integration/deployment changes are allowed;
- it must require project-state/handoff updates when status changes;
- it must require a SESSION REPORT before the next NEXT PROMPT;
- it must be generated from the final verified main HEAD, not from the starting HEAD.

## 13. Project-manager continuity

For end-to-end planning or prioritization tasks, activate:
Primary:
- `mba-business-manager` for business objective, process ownership, KPI/control and priority
- `software-architect` for dependency order, architecture boundaries and technical sequencing

Domain, finance, database, security, DevOps, UX and specialist roles are reviewers or co-primary only when their discipline owns a real decision in that work package.

The next prompt must say who owns which decision. Listing role names without responsibilities is invalid.
