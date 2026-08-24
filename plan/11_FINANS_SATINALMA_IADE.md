# 11 — Finans, Satınalma ve İade

## Cari finans
Authority `account_transactions`.

- satış faturası: müşteri bakiyesini artırır
- tahsilat: müşteri bakiyesini azaltır
- alış faturası: tedarikçi borcunu artırır
- ödeme: tedarikçi borcunu azaltır

Fatura bazlı settlement/open-item core değildir.

## Tahsilat / Ödeme
V16.3 ödeme türleri:
- Nakit
- Banka Havale/EFT
- POS
- Sanal POS
- Çek
- Senet
- Diğer

Seçilen tipe göre alanlar açılır. Ekran işlem öncesi ve sonrası cari bakiyeyi gösterir.

## POS
Gross tahsilat cari borcunu azaltır. Komisyon gross tutardan gizlice düşülmez; ayrı gider/treasury etkisi olarak muhasebeleştirilir.

## Kasa
Kasa Hareketleri ana çalışma ekranıdır. Kasalar ve Kasa Sayımı buradan açılır.

Kasa sayımı:
- sistem bakiyesi
- banknot/bozuk para adetleri
- sayılan toplam
- fark
- fark açıklaması
- taslak/tamamlama

## Banka
Banka Hareketleri içinden hesaplar, ekstre import ve mutabakat açılır.

Ekstre akışı:
`Dosya Seç → Önizleme → Eşleştirme → İçe Aktar`.

V1 formatları: Excel, CSV, MT940.

Duplicate satır fingerprint/reference ile tekrar aktarılmaz.

## Virman
Kaynak ve hedef treasury hesabı arasında atomik çift hareket. Aynı hesap seçilemez. Döviz farkı varsa açık kur/fark kuralı uygulanır.

## Gider
Gider treasury çıkışı ve gerekiyorsa cari/masraf kategorisi etkisi üretir. Belge/fiş eki desteklenir.

## Satınalma
`Sipariş → Mal Kabul → Alış Faturası → Ödeme`.

Mal kabul stock IN; alış faturası cari etkidir. Bu ikisi aynı olay sayılmaz.

## Çek/Senet
Received/issued ayrımı ve tip bazlı state machine vardır. Front/back scan, physical location ve history saklanır. Settlement tek kez finans etkisi üretir.

## İade
### Satış iadesi
Kaynak satış belgesini referanslar; kabul edilen miktar kadar ters stok ve ters cari etkisi üretir.

### Alış iadesi
Tedarikçiye iade edilen miktar kadar stock OUT ve cari düzeltmesi üretir.

### E-Ticaret/RMA
Talep ile fiziksel ürün gelişi ayrılır. İnceleme/karar sonrası stok ve finans etkisi kesinleşir.

## Reversal
Posted finans hareketi silinmez. Reversal ters ledger kayıtları oluşturur ve orijinal kayda bağlanır.
