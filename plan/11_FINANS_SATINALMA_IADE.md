# 11 — Finans, Satınalma ve İade V4

## 1. Satınalma quantity authority
PurchaseOrderLine:
- ordered
- cancelled
- received
- invoiced
- remaining_to_receive
- remaining_to_invoice

Formüller:
`remaining_to_receive = ordered - cancelled - received_not_reversed`
`remaining_to_invoice = ordered - cancelled - invoiced_not_reversed`

Over-receipt ve over-invoice default BLOCK. Kısmi kabul/faturalama normaldir.

## 2. Satınalma akışı
`Satınalma Siparişi → Mal Kabul ve/veya Alış Faturası → Ödeme → İade`.

Mal Kabul fiziksel stock-in authority'sidir. Alış faturası cari financial effect authority'sidir. Aynı quantity iki kez stock IN üretemez.

## 3. Cari finans authority
`account_transactions`.
- satış faturası müşteri bakiyesini artırır
- tahsilat azaltır
- alış faturası tedarikçi borcunu artırır
- ödeme azaltır

OpenItem/fatura bazlı settlement core değildir.

## 4. Tahsilat / Ödeme
V16.3 kullanıcı-visible tipleri:
- Nakit
- Banka Havale/EFT
- POS
- Sanal POS
- Çek
- Senet
- Diğer

PaymentMethod/PaymentType tanımları kontrollü config ile genişletilebilir. Seçilen type kendi alanlarını/validation'ını açar. Ekran işlem öncesi ve sonrası cari bakiyeyi gösterir.

## 5. Nakit
Nakit işlem ilgili CashAccount movement'ını üretir. Makbuz/referans tutulabilir. Cari + kasa etkisi aynı transaction'dadır.

## 6. Banka
Banka tahsilat/ödeme ilgili BankAccount movement'ını üretir. Havale/EFT için gerektiğinde:
- banka/hesap
- referans
- valör
- karşı taraf
- açıklama
snapshot tutulur.

## 7. POS / Sanal POS
POS tahsilatında:
- cari effect = gross tahsilat
- komisyon = ayrı gider/POS effect
- net banka settlement = bank movement

Net settlement cariyi ikinci kez etkilemez. Sanal POS kanal/sipariş referansı taşıyabilir.

## 8. Treasury account currency
Her Cash/Bank hesabı bir book currency taşır. Gerekirse transaction:
- account currency amount
- company base amount
- exchange-rate snapshot

tutar.

Cross-currency işlem explicit FX policy olmadan silent çevrilmez.

## 9. Virman
Same-currency: kaynak çıkış + hedef giriş atomik çift hareket; toplam değer korunur ve aynı hesap seçilemez.

Cross-currency ihtiyaç varsa kur kaynağı, rounding ve FX difference kuralı `19_ACIK_KARARLAR.md` kapanmadan uygulanmaz.

## 10. Gider
Basic gider:
- kategori
- kasa/banka/POS source account
- belge/referans/ek
- tarih/açıklama

taşıyabilir. Cari zorunlu değildir.

## 11. Kasa Sayımı
Ekran:
- kasa
- tarih
- sayımı yapan
- sistem bakiyesi
- kupür/adet
- sayılan toplam
- fark
- açıklama

Fark varsa açıklama zorunlu. Taslak effect yaratmaz; Tamamla exactly-once adjustment yazar.

## 12. Banka ekstresi import
Canlı banka API V1'de yoktur.

Formatlar:
- Excel
- CSV
- MT940

Akış:
`Dosya Seç → Önizleme → Eşleştirme → İçe Aktar`.

Satır alanları:
- Tarih
- Valör
- Açıklama
- Referans
- Giriş
- Çıkış
- Eşleşme
- Durum

Duplicate row stable identity/fingerprint ile ikinci movement oluşturmaz.

## 13. Banka mutabakatı
Statement row existing bank movement ile eşleşirse ikinci movement yaratılmaz. Unmatch/rematch history audit edilir.

## 14. Çek / Senet
### Alınan
Müşteriden teslim/posting anında cari bakiyesini azaltır; instrument Portföyde başlar. Bankada tahsil ikinci cari effect değildir.

### Verilen
Tedarikçiye teslim/posting anında borcu azaltır. Bankadan ödeme ikinci cari effect değildir.

Karşılıksız/ödenmeme/iptal reversal ile bakiyeyi yeniden açar. Front/back scan, physical location ve lifecycle history saklanır.

## 15. Satınalma maliyeti
Mal Kabul provisional/known purchase cost ile stock-in yapabilir. SupplierInvoice fiyat farkı gerekiyorsa original receipt/source lineage'a bağlanır.

Aynı ekonomik fark hem inventory adjustment hem FX difference olarak double-count edilmez. Ayrıntı `21_MALIYETLENDIRME.md`.

## 16. İade
### Satış iadesi
Kaynak satış belgesini/line'ı referanslar. Eligible quantity aşılmaz. Physical stock return ile financial refund/correction kendi authority'lerinde effect üretir.

### Alış iadesi
Original receipt/cost/source lineage korunur. Stock OUT ve cari düzeltme duplicate edilmez.

### E-Ticaret/RMA
Talep ile fiziksel ürün gelişi ayrıdır. İnceleme/karar sonrası stock/finance effect kesinleşir.

## 17. Reversal
Posted finans hareketi silinmez. Reversal ters ledger kayıtları oluşturur, original kayda bağlanır ve tekrar çalıştırmada duplicate effect üretmez.

## 18. Müşteri risk / exposure
Risk limiti cari ticari ayarıdır. Exposure hesaplanırken confirmed order commitment ile invoice balance aynı ekonomik yükümlülük için double-count edilmez. Override ayrı permission + reason + audit gerektirebilir.

## 19. Full Accounting boundary
TDHP/general ledger/e-Defter Mars V1 core değildir. Mars ön muhasebe ve operational finance correctness sağlar; e-Fatura/e-Arşiv provider integration ayrı lifecycle'dır.
