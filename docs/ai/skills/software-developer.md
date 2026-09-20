# Skill: Yazılım Geliştirici

## 1. Misyon
Onaylanmış iş kurallarını ve mimariyi en küçük doğru değişiklikle çalışan koda dönüştürür. Görevi tasarımı kafasına göre değiştirmek değil, repo gerçeklerine bağlı uygulama yapmaktır.

## 2. Kod yazmadan önce zorunlu sıra
1. docs/plan/ai-cmd.md oku.
2. docs/ai/skill-router.md oku.
3. İlgili skill dosyalarını oku.
4. İlgili modül planını oku.
5. İlgili DB belgelerini oku.
6. Değiştirilecek mevcut kodu oku.
7. Target branch'in main olduğunu doğrula.
8. Görev kapsamını yaz.
9. UNKNOWN/BLOCKED var mı kontrol et.
10. Sonra kod yaz.

Bu sıra atlanamaz.

## 3. Kapsam disiplini
- Kullanıcının istemediği refactor yapılmaz.
- Aynı anda ilgisiz modül değiştirilmez.
- "hazır buradayken" mantığıyla kapsam genişletilmez.
- Aynı işi yapan ikinci servis/helper oluşturulmaz.
- Mevcut abstraction okunmadan yenisi yazılmaz.

## 4. C# kuralları
- Nullable reference type semantiğine uy.
- Para/miktar için decimal kullan.
- float/double muhasebe hesaplarında kullanma.
- async I/O zincirini sync-over-async ile bozma.
- CancellationToken gerektiği yerde geçir.
- exception yutma.
- generic Exception fırlatmayı varsayılan yapma.
- domain exception ile teknik exception'ı ayır.
- magic string/number azalt.
- immutable/value object gereken yerde düşün.
- DateTime/DateTimeOffset kullanımını açıklaştır.

## 5. Domain uygulama kuralları
- Invariant UI'da değil domain/application tarafında zorlanır.
- Handler yalnız orchestration yapmalı; domain hesabı helper/controller'a dağılmamalı.
- Kaynak belge ilişkileri korunmalı.
- Kısmi işlem miktarları açıkça takip edilmeli.
- Posted kayıt sessizce update/delete edilmemeli.
- Cancellation ile reversal karıştırılmamalı.

## 6. Transaction ve side-effect
- Atomik DB değişiklikleri tek transaction.
- Dış provider çağrısı transaction'ın gerçeği değildir.
- Mail/SMS/WhatsApp/marketplace gibi yan etkiler outbox/worker ile ayrılmalı.
- Idempotency gereken işlemlerde unique constraint veya eşdeğer DB garantisi kullanılmalı.
- Retry duplicate kayıt üretememeli.

## 7. API kuralları
- /api/v1
- server-side authorization
- deterministik validation
- idempotent komutlarda duplicate davranışı tanımlı
- public UUID kullanımı
- internal ID gereksiz sızdırılmaz
- pagination/filter contract açık
- error response tutarlı
- 404/409/422/403 gibi statüler anlamsal kullanılır

## 8. DB kuralları
- Schema sadece migration ile değişir.
- Migration adı amacı anlatır.
- Destructive migration açıkça işaretlenir.
- Authoritative stock/balance kolonları yaratılmaz.
- Snapshot, ledger, projection ayrımı korunur.
- Index körlemesine değil sorguya göre eklenir.

## 9. Frontend kuralları
- Mars.UI bileşenleri yeniden kullanılır.
- Inline CSS/JS yığını oluşturulmaz.
- Aynı veri için çoklu network request azaltılır.
- UI validation server validation yerine geçmez.
- F2/lookup ve keyboard davranışı standartlara uyar.
- Mobile görünüm desktop'ın küçültülmüş hali değildir.

## 10. Error handling
Her hata için:
- kullanıcıya ne gösterilir?
- log'a ne yazılır?
- retry olur mu?
- transaction rollback olur mu?
- audit/outbox etkisi var mı?
belirlenir.

## 11. Hızlı test politikası
Normal geliştirmede yalnız:
- compile/build
- değişen alan unit/invariant
- gerekli küçük smoke
- migration/contract hızlı kontrol
çalıştırılır.

Full suite, E2E, load, uzun security testleri FULL TEST DAY'e bırakılır.

## 12. Edge-case checklist
- null/empty
- zero/negative
- max precision
- duplicate submit
- concurrent update
- stale state
- partial quantity
- over-processing
- cancel/reverse
- retry after timeout
- timezone/day boundary
- unauthorized tenant/company
- deleted/inactive master reference

## 13. Yasaklar
- main dışında branch oluşturma
- PR oluşturma
- TODO'yu done sayma
- mock sonucu gerçek provider sonucu diye raporlama
- var olmayan field/table/API uydurma
- compile etmeden "çalışıyor" deme
- başka projeden kör kod taşıma

## 14. Zorunlu çıktı
Değişiklik sonunda:
- değişen dosyalar
- neden değişti
- hızlı kontroller
- kalan heavy-test maddeleri
- UNKNOWN/BLOCKED
- migration/contract etkisi

## 15. Definition of Done
- kod mevcut mimariye uyuyor
- scope dışı değişiklik yok
- hızlı kontroller geçti
- gerekli migration/doküman güncel
- gerçek repo durumu raporlandı
