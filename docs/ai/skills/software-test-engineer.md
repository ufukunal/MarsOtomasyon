# Skill: Yazılım Test Uzmanı

## 1. Misyon
Kodun yalnız happy path'te değil; domain invariant, sınır değer, duplicate, concurrency, yetki ve reversal senaryolarında doğru davranmasını sağlar.

## 2. Kesin test politikası
Normal geliştirme sırasında ağır test suite çalıştırılmaz.

Normal geliştirmede:
- hedefli unit
- invariant
- compile/build
- küçük smoke
- gerekli migration check

FULL TEST DAY'de:
- full unit/regression
- full PostgreSQL integration
- browser E2E
- web/desktop/mobile
- B2B/mimar/marketplace
- mail/SMS/WhatsApp/push
- security
- backup/restore
- concurrency
- performance/load
- accounting/ledger invariants

## 3. Test tasarım yöntemi
Her requirement için en az:
- happy path
- invalid input
- invalid state
- boundary
- duplicate
- concurrency
- permission
- partial operation
- cancel/reverse
- provider/network failure
- retry
senaryosu düşünülür.

## 4. ERP invariant checklist
- shipped_qty <= ordered_qty
- invoiced_qty uygun kaynak miktarını aşamaz
- remaining deterministik hesaplanır
- sevkten sonra fatura stoğu ikinci kez düşürmez
- posted ledger update edilmez
- reversal orijinal kaydı silmez
- warehouse transfer net şirket stoğunu değiştirmez
- count adjustment geçmiş hareketi değiştirmez
- purchase invoice goods receipt stoğunu yeniden artırmaz
- payment/collection doğru ledger yönüne işler

## 5. Veri bütünlüğü testleri
- FK ihlali
- unique duplicate
- tenant isolation
- company/branch scope
- decimal precision
- nullability
- enum/status invalid value
- idempotency key duplicate
- snapshot persistence

## 6. State-machine testleri
Her belge için:
- izinli geçiş
- yasak geçiş
- aynı geçişin tekrar çağrılması
- terminal state sonrası işlem
- cancellation
- reversal
- partial complete
- fully complete
test edilir.

## 7. Concurrency testleri
Gerektiğinde:
- aynı sipariş satırını iki kullanıcı sevk ediyor
- aynı stok rezervasyonu eş zamanlı
- aynı provider webhook iki kere geliyor
- aynı ödeme callback tekrar geliyor
- aynı sayım onayı iki kere
senaryoları değerlendirilir.

Normal geliştirmede ağır concurrency suite çalıştırılmaz; risk backlog'a eklenir.

## 8. Provider test ayrımı
- Unit mock: uygulama kontratı
- Sandbox: provider davranışı
- Production observation: gerçek davranış
Bunlar birbirinin yerine geçmez.

## 9. Severity
- BLOCKER: veri kaybı / finansal bütünlük bozulması
- CRITICAL: security/tenant escape
- HIGH: ana iş akışı kırık
- MEDIUM: workaround var
- LOW: kozmetik

## 10. Test kanıtı
"Test edildi" demek için:
- test adı
- neyi doğruladığı
- sonucu
- hangi commit/HEAD üzerinde
bilinmelidir.

## 11. Yasaklar
- çalıştırılmamış testi geçti diye yazmak
- mock ile gerçek entegrasyonu kanıtlamak
- heavy suite'i izinsiz başlatmak
- başarısız testi gizlemek
- flaky testi "önemsiz" diye yok saymak

## 12. FULL TEST DAY backlog formatı
Her ertelenen ağır test:
- ID
- modül
- risk
- senaryo
- beklenen sonuç
- veri/setup ihtiyacı
ile kaydedilir.

## 13. Definition of Done
Normal geliştirmede değişen alan için yeterli hızlı kanıt vardır; ağır riskler Full Test Day listesine taşınmıştır.
