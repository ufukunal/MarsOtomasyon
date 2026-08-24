# 06 — İş Kuralları ve Invariantlar

## Genel
- Finalized/posted kayıt normal CRUD ile değiştirilemez.
- Aynı business action retry edildiğinde ikinci finans/stok etkisi oluşamaz.
- Cross-domain posting tek transaction içinde atomiktir.
- Ledger geçmişi delete/update ile düzeltilmez; reversal/correction kullanılır.

## Cari
Authority `account_transactions`.
- satış faturası müşteri borç/bakiyesini artırır
- tahsilat azaltır
- alış faturası tedarikçiye borcu artırır
- ödeme azaltır
- tahsilat/ödeme fatura eşleştirmesi zorunlu değildir
- sıfır bakiye ayrı nötr durumdur

## Satış siparişi
Her satır için:
- ordered >= 0
- dispatched >= 0
- invoiced >= 0
- remaining >= 0
- dispatched ve invoiced sipariş miktarını business kuralı dışında aşamaz

Kısmi işlem desteklenir. Kullanıcı sipariş/faturalanan/sevk/kalan progress'ini görür.

## Satış faturası posting
Tek transaction:
1. draft/finalize validation
2. invoice posted
3. ilgili order invoiced/remaining update
4. gerekli stock OUT
5. account transaction
6. outbox

Aynı fiziksel çıkış daha önce dispatch ile authoritative olarak oluşmuşsa ikinci stock OUT üretilmez; stock policy belge kaynağına göre tek otorite seçer.

## İrsaliye/sevkiyat
Fiyat/KDV belgesi değildir. Sipariş miktarı, önceki sevk, bu sevk, kalan ve taşıma bilgisine odaklanır.

## Alış
- PurchaseOrder progress: ordered/received/invoiced/remaining
- GoodsReceipt physical stock IN üretir
- SupplierInvoice account payable etkisi üretir
- Mal kabul ve fatura birbirinden farklı tarihlerde olabilir

## Stok
- `stock_movements` authority
- reservation movement değildir
- transfer kaynak OUT + hedef IN tek business transaction'dır
- count farkı onay/posting sonrası adjustment hareketi üretir
- negatif stok politikası merkezi ayardır; bypass edilemez

## Kasa/Banka
Tahsilat/ödeme transaction'ı hem cari hem treasury etkisini atomik üretir.

POS: gross tahsilat cari etkisidir; komisyon ayrı gider/banka/POS etkisidir.

Virman: kaynak çıkış + hedef giriş atomiktir; toplam değer korunur (kur farkı ayrı kural).

## Çek/Senet
State transition yalnız izinli durumlar arasında yapılır. Aynı instrument iki kez tahsil/ödeme etkisi üretemez. Settlement concurrency kilitlenir.

## Üretim
- material issue stock OUT
- finished goods receipt stock IN
- aynı üretim emri satırı miktar sınırlarını aşamaz
- completion açık uyumsuz miktar varsa engellenir veya açıklamalı override permission ister

## Fason
Gönderilen malzeme, gelen mamul, fire/eksik ve kalan matematiksel olarak uzlaştırılmalıdır. Ayrı stok authority yoktur.

## İthalat maliyeti
Konteyner/sevkiyat genel giderleri seçilen dağıtım anahtarına göre ürünlere dağıtılır. Dağıtılmamış tutar kapanışta sıfır olmalı veya açık istisna olarak raporlanmalıdır.

## Entegrasyon
External order/webhook kimliği channel kapsamında unique olmalıdır. Retry duplicate sipariş/fatura/stok hareketi oluşturamaz.

## Para/KDV
Posted belgede fiyat, iskonto, KDV oranı, kur ve toplam snapshot'tır. Master değişikliği geçmiş belgeyi yeniden hesaplamaz.
