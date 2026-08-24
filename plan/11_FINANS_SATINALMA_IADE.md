# 11 — Finans, Satınalma ve İade V4.1

## 1. Satınalma quantity authority
PurchaseOrderLine:
- ordered
- cancelled
- accepted
- invoiced
- remaining_to_receive
- remaining_to_invoice

Formüller:
`remaining_to_receive = ordered - cancelled - accepted_not_reversed`
`remaining_to_invoice = ordered - cancelled - invoiced_not_reversed`

Over-receipt/acceptance ve over-invoice BLOCK. Kısmi kabul/faturalama normaldir.

## 2. Satınalma akışı
`Satınalma Siparişi → Mal Kabul ve/veya Alış Faturası → Ödeme → İade`.

Mal Kabul fiziksel stock-in authority'sidir. Alış faturası cari financial effect authority'sidir. Aynı quantity iki kez stock IN üretemez.

## 3. Mal Kabul quantity + kalite
Bir GoodsReceiptLine fiziksel gelen miktarı gerekirse:
- `accepted_qty`
- `pending_quality_qty`
- `rejected_qty`
olarak böler.

`physical_received = accepted + pending + rejected`.

PurchaseOrder progress yalnız accepted quantity ile kapanır. Pending/rejected quantity blocked/quarantine custody'de bulunabilir ve available değildir. Pending sonradan accepted/rejected reclassification yapar; ikinci physical stock IN yaratmaz.

## 4. Cari finans authority
`account_transactions`.
- satış faturası customer/financial counterparty bakiyesini artırır
- tahsilat/clearing payout azaltır
- alış faturası tedarikçi borcunu artırır
- ödeme azaltır

OpenItem/fatura bazlı settlement core değildir.

Her Account V1'de tek book currency taşır; AccountTransaction aynı currency'dedir. Base-currency snapshot ayrıca tutulabilir.

## 5. Treasury authority
Kasa, banka ve POS parasal bakiye authority'si append-oriented `treasury_movements` ledger'ıdır.

Source kayıtları:
- Collection
- Payment
- Expense
- Transfer
- POSSettlement
- CashCount adjustment
- statement/import-originated controlled movement

deterministic source-effect ile treasury movement üretir. Cash/Bank balance keyfi UPDATE edilmez.

## 6. Tahsilat / Ödeme
V16.3 kullanıcı-visible tipleri:
- Nakit
- Banka Havale/EFT
- POS
- Sanal POS
- Çek
- Senet
- Diğer

PaymentMethod/PaymentType kontrollü config ile genişletilebilir. Seçilen type kendi alanlarını/validation'ını açar. Ekran işlem öncesi ve sonrası cari bakiyeyi gösterir.

## 7. Nakit
Nakit işlem ilgili CashAccount treasury movement'ını üretir. Makbuz/referans tutulabilir. Cari + kasa etkisi aynı transaction'dadır.

## 8. Banka
Banka tahsilat/ödeme ilgili BankAccount treasury movement'ını üretir. Havale/EFT için gerektiğinde banka/hesap, referans, valör, karşı taraf ve açıklama snapshot tutulur.

## 9. POS / Sanal POS
POS tahsilatında:
- cari effect = gross tahsilat
- POS lifecycle = `Pending → Settled | Reversed/Chargeback`
- komisyon = ayrı gider/treasury effect
- net banka settlement = bank treasury movement

Net settlement cariyi ikinci kez etkilemez. Sanal POS kanal/sipariş referansı taşıyabilir. Settlement row/provider reference idempotent'tir.

## 10. Treasury account currency
Her Cash/Bank hesabı bir book currency taşır. Gerekirse transaction:
- account currency amount
- company base amount
- exchange-rate snapshot

tutar.

Cross-currency işlem explicit FX policy olmadan silent çevrilmez.

## 11. Virman
Same-currency: kaynak çıkış + hedef giriş atomik çift treasury movement; toplam değer korunur ve aynı hesap seçilemez.

Cross-currency ihtiyaç varsa kur kaynağı, rounding ve FX difference kuralı `19_ACIK_KARARLAR.md` A-07 kapanmadan uygulanmaz.

## 12. Gider
Basic gider:
- kategori
- kasa/banka/POS source account
- belge/referans/ek
- tarih/açıklama

taşıyabilir. Cari zorunlu değildir.

## 13. Kasa Sayımı
Ekran:
- kasa
- tarih
- sayımı yapan
- sistem bakiyesi
- kupür/adet
- sayılan toplam
- fark
- açıklama

Fark varsa açıklama zorunlu. Taslak effect yaratmaz; Tamamla exactly-once treasury adjustment yazar.

## 14. Banka ekstresi import
Canlı banka API V1'de yoktur.

Formatlar:
- Excel
- CSV
- MT940

Akış:
`Dosya Seç → Önizleme → Eşleştirme → İçe Aktar`.

Duplicate row stable identity/fingerprint ile ikinci treasury movement oluşturmaz.

## 15. Banka mutabakatı
Statement row existing bank treasury movement ile eşleşirse ikinci movement yaratılmaz. Unmatch/rematch history audit edilir.

## 16. Çek / Senet
### Alınan
Müşteriden teslim/posting anında cari bakiyesini azaltır; instrument Portföyde başlar. Bankada tahsil ikinci cari effect değildir.

### Verilen
Tedarikçiye teslim/posting anında borcu azaltır. Bankadan ödeme ikinci cari effect değildir.

### Alınan instrument'ın ciro edilmesi
Alınan çek/senet tedarikçiye ciro edildiğinde:
- original customer cari effect tekrar yazılmaz,
- ilgili supplier payable bir kez azaltılır,
- instrument holder/physical location history güncellenir,
- bankada tahsil ayrıca supplier/customer cari effect üretmez.

Karşılıksız/ödenmeme/iptal durumda instrument'ın bulunduğu lifecycle'a göre supplier effect ve gerekiyorsa original customer effect explicit reversal lineage ile yeniden açılır.

Front/back scan, physical location ve lifecycle history saklanır.

## 17. Marketplace financial clearing
Marketplace legal/end-customer snapshot finansal Account olmak zorunda değildir.

Her marketplace channel/account için bir `Marketplace Clearing Account` ilişkilendirilebilir:
- marketplace invoice → clearing receivable artar,
- provider payout → clearing receivable azalır + banka treasury movement oluşur,
- komisyon/hizmet/kargo/ceza/chargeback → ayrı fee/expense/settlement effect,
- refund/return original order/invoice/settlement lineage'a bağlanır.

Provider settlement satırı stable external identity/fingerprint ile exactly-once işlenir. Clearing opening + invoice/refund ± adjustment − payout = closing reconciliation raporlanabilir olmalıdır.

WooCommerce channel config ödeme modeline göre direct Account veya clearing Account kullanabilir. Mars B2B her zaman pre-bound Account kullanır.

## 18. Satınalma maliyeti
V1 costing moving weighted average'dır. Mal Kabul provisional/known purchase cost ile stock-in yapabilir. SupplierInvoice fiyat farkı gerekiyorsa original receipt/source lineage'a bağlanır.

Aynı ekonomik fark hem inventory adjustment hem FX difference olarak double-count edilmez. Ayrıntı `21_MALIYETLENDIRME.md`.

## 19. İade
### Satış iadesi
Kaynak satış belgesini/line'ı referanslar. `returnable_qty` aşılmaz. Physical stock return ile financial refund/correction kendi authority'lerinde effect üretir.

### Alış iadesi
Original receipt/cost/source lineage korunur. Stock OUT ve cari düzeltme duplicate edilmez.

### E-Ticaret/RMA
M12 yalnız ortak Return/RMA Core'u kurar. Provider talep/status/cargo connector'ları M17/M18 adapter capability'leriyle eklenir. Talep ile fiziksel ürün gelişi ayrıdır; inceleme/karar sonrası stock/finance effect kesinleşir.

## 20. Reversal
Posted finans hareketi silinmez. Reversal ters ledger kayıtları oluşturur, original kayda bağlanır ve tekrar çalıştırmada duplicate effect üretmez. Sales/Purchase progress net counters reversal sonrası yeniden açılır.

## 21. Müşteri risk / exposure
Risk limiti cari ticari ayarıdır. Exposure hesaplanırken confirmed order commitment ile invoice balance aynı ekonomik yükümlülük için double-count edilmez. Override ayrı permission + reason + audit gerektirebilir.

B2B order submit sırasında aynı server-side risk/exposure policy uygulanır; client yalnız uyarıyı gizleyerek bypass edemez.

## 22. Posting period
Financial/stock posting `posting_date` döneminin açık olmasını gerektirir. Closed/frozen period BLOCK; override ayrı permission + reason + audit ve policy gerekiyorsa approval ister.

## 23. Full Accounting boundary
TDHP/general ledger/e-Defter Mars V1 core değildir. Mars ön muhasebe ve operational finance correctness sağlar; e-Fatura/e-Arşiv provider integration ayrı lifecycle'dır.
