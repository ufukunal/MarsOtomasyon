# 09 — Ticari Akışlar

## Satış ana akışı
`Teklif → Satış Siparişi → İrsaliye/Sevkiyat → Satış Faturası → Tahsilat → İade`

Her belge bağımsız source/reference taşır; dönüşümde kaynak satır ilişkisi korunur.

### Teklif
- revizyon destekler
- onaylanan teklif siparişe dönüşebilir
- teklif stok/finans ledger etkisi üretmez

### Satış Siparişi
Satır progress:
`Sipariş | Sevk | Faturalanan | Kalan`.

Kısmi sevk/faturalama desteklenir. Kalan sıfıra ulaştığında satır tamamlanır.

### İrsaliye/Sevkiyat
Operasyonel sevk belgesidir. V16.3'te fiyat/KDV odaklı değildir. Sipariş miktarı, önceki sevk, bu sevk, kalan ve nakliye/sevk bilgileri gösterilir.

Stok çıkış authority'si implementasyon politikasıyla tek noktada seçilir; dispatch ve fatura aynı fiziksel çıkışı iki kez düşemez.

### Satış Faturası
Kesinleştirme atomiktir:
- fatura posted
- sipariş invoiced/remaining güncellenir
- gerekiyorsa stock OUT
- cari hareketi
- outbox

KDV Sıfırla aksiyonu line VAT oranlarını sıfırlar ve totals recalculation yapar; posting öncesi kullanılır.

### Tahsilat
Faturaya zorunlu kapatma yapmaz. Cari bakiyeyi azaltır ve seçilen kasa/banka/POS/çek/senet etkisini üretir.

## Alış ana akışı
`Satınalma Siparişi → Mal Kabul → Alış Faturası → Ödeme → İade`

### Satınalma Siparişi
Progress: ordered/received/invoiced/remaining.

### Mal Kabul
Fiziksel stok girişidir. Satırda sipariş, önceki kabul, bu kabul, kalan ve kalite sonucu görünür.

### Alış Faturası
Tedarikçi cari borç etkisini üretir. Mal kabul olmadan/önce/sonra fatura senaryoları business policy ile desteklenebilir; duplicate stok girişi üretmez.

### Ödeme
Tedarikçi bakiyesini azaltır; fatura eşleştirmesi core için zorunlu değildir.

## İadeler
Satış ve alış iadeleri source belgeyi referanslar, ancak kendi stok/finans etkilerini ayrı transaction olarak üretir. İade edilen miktar kaynak satır limitlerini aşamaz.

## Proforma
Finans/stok authority üretmeyen çıktı/ön belge olarak ele alınır.

## Belge dönüşüm ilkesi
Kaynak belge değişmez; hedef belge snapshot alır ve source relation tutar. Bir belgenin kısmi dönüşümlerinin toplamı kaynak miktar limitlerini aşamaz.
