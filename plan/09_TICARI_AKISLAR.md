# 09 — Ticari Akışlar V4.1

## Satış ana akışı
`Teklif → Satış Siparişi → İrsaliye/Sevkiyat → Satış Faturası → Tahsilat → İade`

Her belge bağımsız source/reference taşır; dönüşümde kaynak satır ilişkisi korunur.

### Teklif
- revizyon destekler
- onaylanan teklif siparişe dönüşebilir
- teklif stok/finans ledger etkisi üretmez
- fiyat/KDV/iskonto hesabı `06` ve K-043 contract'ına uyar

### Satış Siparişi
Satır progress:
`Sipariş | Net Sevk | Net Faturalanan | Kalan`.

Kısmi sevk/faturalama desteklenir. Reversal ilgili net counter'ı düşürür ve remaining'i yeniden açabilir. Over-cancel/over-dispatch/over-invoice bloklanır.

Reservation available-stock sınırına uyar; implicit negative/backorder yoktur.

### İrsaliye/Sevkiyat
Operasyonel sevk belgesidir. V16.3'te fiyat/KDV odaklı değildir. Sipariş miktarı, önceki net sevk, bu sevk, kalan ve nakliye/sevk bilgileri gösterilir.

**Varsayılan fiziksel stock OUT authority sevkiyat posting'dir.** Dispatch stock OUT yaptıysa bağlı invoice aynı quantity'yi tekrar düşmez.

### Satış Faturası
Kesinleştirme atomiktir:
- posting-period/validation
- invoice posted
- sipariş net invoiced/remaining güncellenir
- irsaliyesiz direct invoice fiziksel çıkışı temsil ediyorsa stock OUT
- cari/financial counterparty hareketi
- outbox

Marketplace invoice legal customer snapshot ile financial clearing counterparty'yi ayırabilir.

KDV Sıfırla line VAT oranlarını sıfırlar ve totals recalculation yapar; zero-tax reason snapshot gerekir.

### Tahsilat
Faturaya zorunlu kapatma yapmaz. Cari bakiyeyi azaltır ve seçilen kasa/banka/POS/çek/senet etkisini `treasury_movements`/instrument authority üzerinden üretir.

Marketplace payout klasik müşteri tahsilatı gibi kör işlenmez; clearing settlement contract'ına uyar.

## Alış ana akışı
`Satınalma Siparişi → Mal Kabul → Alış Faturası → Ödeme → İade`

### Satınalma Siparişi
Progress: ordered/accepted/invoiced/remaining.

### Mal Kabul
Fiziksel stok giriş authority'sidir. Fiziksel gelen quantity accepted/pending-quality/rejected olarak bölünebilir. PurchaseOrder remaining yalnız accepted quantity ile kapanır.

### Alış Faturası
Tedarikçi cari borç etkisini üretir. Mal kabul olmadan/önce/sonra fatura senaryoları desteklenebilir; duplicate stok girişi üretmez. Purchase price difference original receipt/cost lineage'a bağlanır.

### Ödeme
Tedarikçi bakiyesini azaltır; fatura eşleştirmesi core için zorunlu değildir.

## İadeler
Satış ve alış iadeleri source belge/line'ı referanslar, ancak kendi stok/finans etkilerini ayrı transaction olarak üretir. `returnable_qty` kaynak limitini aşamaz. Reversed return net returned counter'ı yeniden açabilir.

Marketplace/RMA provider talebi M12 Return Core'a normalize edilir; provider connector M17/M18'de yaşar.

## Proforma
Finans/stok authority üretmeyen çıktı/ön belge olarak ele alınır.

## Belge dönüşüm ilkesi
Kaynak belge değişmez; hedef belge snapshot alır ve source relation tutar. Bir belgenin kısmi dönüşüm/reversal toplamları kaynak quantity limitlerini aşamaz.

## Source-effect özeti
- SalesOrder: reservation/progress; ledger yok
- Dispatch: default physical stock OUT
- SalesInvoice: account effect; direct-no-dispatch ise stock OUT
- GoodsReceipt: physical stock IN + accepted/pending/rejected custody
- SupplierInvoice: supplier account effect; second stock IN yok
- Collection/Payment: AccountTransaction + TreasuryMovement/instrument effect
- Return: source-eligible stock/finance reversal/correction effects
