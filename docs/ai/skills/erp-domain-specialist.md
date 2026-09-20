# Skill: ERP Alan Uzmanı

## 1. Misyon
ERP'deki ticari belge, stok, cari, satınalma, satış, üretim ve operasyon akışlarının gerçek iş anlamını korur. UI isimlerinden değil, işlemin ekonomik/fiziksel etkisinden düşünür.

## 2. Zorunlu çalışma yöntemi
Her form/işlem için şu kontrat doldurulur:
- Amaç
- Kim kullanır
- Hangi veriyi okur
- Hangi kayıtları oluşturur/değiştirir
- DOC etkisi
- RES etkisi
- STOCK etkisi
- ACCOUNT etkisi
- CASH/BANK etkisi
- COST etkisi
- Kaynak belge
- Hedef belge
- Kısmi işlem
- Durum geçişleri
- İptal/düzeltme
- Yetki/onay
- Audit
- Outbox/integration etkisi

Eksik alan varsa tahmin edilmez.

## 3. Satış akışı
### Teklif
- ticari niyet belgesi
- stok hareketi yok
- rezervasyon yok
- cari alacak yok
- revizyonla geçmiş teklif korunabilir
- siparişe dönüşüm ilişkisi saklanır

### Satış siparişi
- onay sonrası rezervasyon oluşturabilir
- fiziksel stok çıkışı değildir
- cari alacak doğurmaz
- ordered/shipped/invoiced/remaining miktarlar izlenir
- kısmi sevk ve kısmi fatura desteklenir

### Sevkiyat / irsaliye
- fiziksel stock OUT burada
- rezervasyon çözülür/azalır
- siparişe line-level link korunur
- partial shipment mümkündür
- iptal/reversal fiziksel stoğu geri etkiler

### Satış faturası
- ACCOUNT receivable oluşturur
- sevkten geldiyse stock ikinci kez düşmez
- doğrudan faturada stock etkisi ayrıca kuralla belirlenir
- vergi/kur/adres/product snapshot korunur

### Tahsilat
- account receivable azaltır
- cash veya bank artırır
- kısmi kapama olabilir
- hangi belge/borç kalemini kapattığı izlenebilir

### Satış iadesi
- fiziksel iade ile finansal credit/refund ayrı düşünülür
- kalite sonrası AVAILABLE/REWORK/QUARANTINE/SCRAP kararı olabilir

## 4. Satınalma akışı
### Satınalma siparişi
- commitment
- stok yok
- supplier payable yok

### Mal kabul
- physical stock IN
- kalite/karantina olabilir
- supplier payable tek başına oluşmaz
- purchase order line ile partial receipt takip edilir

### Tedarikçi faturası
- supplier payable oluşturur
- mal kabul yapıldıysa stock tekrar artmaz
- 3-way match: PO / Receipt / Invoice
- fiyat/miktar sapması ayrıca görünür

### Ödeme
- supplier liability azaltır
- bank/cash OUT

## 5. Depo
- warehouse transfer source OUT + target IN
- şirket toplam stoğu değişmez
- transit desteklenebilir
- stock count farkı adjustment ile
- geçmiş stok hareketi elle silinmez

## 6. Üretim
- production order plan/commitment
- reservation olabilir
- material issue raw stock OUT
- completion/receipt finished stock IN
- WIP ve production cost ayrı izlenir

## 7. Fason
- şirkete ait mal fason lokasyonuna gider; satış değildir
- sent / used / returned / scrap / produced ayrı
- fason hizmet maliyeti mal hareketinden ayrı

## 8. İthalat
- container shipment quantity/location tracking
- landed cost quantity değil valuation etkiler
- bir ürün birden fazla container ile gelebilir
- cost allocation anahtarı açık olmalı

## 9. Commerce
Dış kanallar normalize edilir:
external order -> staging/mapping -> Mars Sales Order -> normal reservation/dispatch/invoice/collection.
Platform başına ayrı muhasebe mantığı yok.

## 10. Belge durumları
Her belge için açık state machine gerekir:
- DRAFT
- PENDING_APPROVAL
- APPROVED
- POSTED/CONFIRMED
- PARTIALLY_COMPLETED
- COMPLETED
- CANCELLED
- REVERSED
Gereksiz tüm state'ler her belgede zorunlu değildir; ihtiyaca göre seçilir.

## 11. Kısmi işlem ilkesi
Satır bazında gerekirse:
- ordered_qty
- reserved_qty
- received_qty
- shipped_qty
- invoiced_qty
- returned_qty
- remaining_qty
ayrımı yapılır.
Türetilen değer ile persisted değer arasında tek gerçek kaynak belirlenir.

## 12. İptal ve düzeltme
- Draft silinebilir mi ayrı karar
- Posted belge hard delete edilmez
- Fiziksel/finansal etki reversal ile geri alınır
- Bağlı sonraki belgeler varsa iptal kuralı açık olmalıdır

## 13. Sorgu/rapor ekranı
Cari Ekstre, Stok Durumu, Satış Raporu gibi ekranlar authoritative mutable tablo değildir; document/ledger/read model üzerinden okur.

## 14. Anti-patternler
- siparişte stok düşürmek
- sevk+faturada stoğu iki kez düşürmek
- mal kabul+alış faturasında stoğu iki kez artırmak
- count sırasında stock = X yapmak
- posted belgeyi sessiz edit etmek
- master card değişince eski belgeyi değiştirmek

## 15. BLOCKED
Şunlar tanımsızsa implementasyon durur:
- fiziksel stok hangi anda değişir
- finansal alacak/borç hangi anda doğar
- partial davranış
- cancel/reversal
- source/target document relation

## 16. Definition of Done
İşlem baştan sona document, reservation, ledger, cost, link, state ve reversal etkileriyle açıklanabiliyor.
