# Skill: Muhasebe ve Finans Uzmanı

## 1. Misyon
MarsOtomasyon'da finansal hareketlerin doğru yön, doğru tarih, doğru para birimi, doğru belge kaynağı ve izlenebilir reversal mantığı ile oluşmasını sağlar.

## 2. Zorunlu inceleme alanları
Her finansal işlemde:
- borç/alacak yönü
- posting tarihi
- belge tarihi
- value date
- currency
- exchange rate
- tax/matrah
- discount
- rounding
- account/counter-account
- partial settlement
- reconciliation
- reversal
- source document
- company/branch
- audit actor
kontrol edilir.

## 3. Cari ledger
Authoritative müşteri/tedarikçi bakiye account_ledger'dan türetilir.

Yasak:
- customer.current_balance authoritative
- supplier.current_balance authoritative

İşlem örnekleri:
- satış faturası: müşteri alacağı artırır
- tahsilat: müşteri alacağını azaltır
- alış faturası: tedarikçi borcunu artırır
- ödeme: tedarikçi borcunu azaltır
- credit note/iade: ilgili bakiyeyi ters yönde etkiler

## 4. Cash/Bank
cash_ledger ve bank_ledger authoritative olmalıdır.

Banka transferi:
- kaynak banka OUT
- hedef banka IN
- aynı currency ise net değer aynı
- farklı currency ise iki para birimi + kur + kur farkı ele alınır

## 5. Çek/Senet
Lifecycle explicit olmalı:
- RECEIVED
- PORTFOLIO
- GIVEN_TO_BANK
- ENDORSED
- COLLECTED
- BOUNCED
- RETURNED
- CANCELLED

Her state'in:
- accounting impact
- cash/bank impact
- customer/supplier impact
ayrı tanımlanması gerekir.

## 6. Vergi/KDV
Her belge için:
- vergi oranı
- matrah
- iskonto öncesi/sonrası baz
- rounding sırası
- tax-inclusive/exclusive fiyat
tanımlıdır.

Belge tarihindeki tax rate snapshot'tır.

## 7. Kur
- belge para birimi
- şirket baz para birimi
- kur tarihi
- kur tipi/kaynağı
- çapraz kur gerekiyorsa kaynak
- realized/unrealized fark ayrımı
belirlenir.

Kur master'ı sonradan değişse bile posted belge kuru değişmez.

## 8. Rounding
Rounding merkezi politika olmalı:
- satır mı belge mi
- kaç decimal
- tax rounding
- currency minor unit
Farklı modüller kendi kafasına göre rounding yapamaz.

## 9. Settlement
Tahsilat/ödeme:
- tek belgeye
- çok belgeye
- avans olarak
- kısmi
uygulanabilir.
Allocation ayrı izlenmelidir.

## 10. Reversal
Posted hareket:
- update edilmez
- delete edilmez
- reverse entry oluşturulur
- original ile bağ korunur
- gerekirse yeni doğru posting yapılır

## 11. Maliyet
### Satınalma
- mal bedeli
- freight
- customs
- insurance
- handling
- other landed costs

### Üretim
- material
- labor
- machine
- overhead
- subcontract

Cost allocation:
- weight
- volume
- value
- quantity
- custom driver
gibi deterministik anahtara bağlanır.

## 12. Yönetim finansı
KPI tanımları veri kaynağıyla birlikte:
- gross margin
- contribution margin
- working capital
- DSO
- DPO
- aging
- inventory value
- channel profitability

## 13. Kontrol listesi
- negative amount yön mü işaret mi?
- debit/credit sign convention tek mi?
- currency mismatch?
- tax rounding?
- partial allocation?
- duplicate posting?
- cancellation effect?
- period lock?
- source document already posted?
- re-post prevention?

## 14. Yasaklar
- float/double
- balance cache'i gerçek saymak
- posted kayıt edit
- sevkiyatı otomatik alacak saymak
- mal kabulü otomatik borç saymak
- aynı belgeyi iki kez post etmek

## 15. BLOCKED
Şunlar belirsizse dur:
- financial recognition point
- reversal rule
- tax treatment
- FX rule
- settlement rule

## 16. Definition of Done
Finansal hareketin kaynağı, yönü, tarihi, currency'si, tax/rounding'i, allocation'ı ve reversal davranışı deterministik.
