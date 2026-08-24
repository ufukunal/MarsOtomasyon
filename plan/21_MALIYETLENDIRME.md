# 21 — Maliyetlendirme V4

## 1. Amaç
MarsOtomasyon ürün, alış, üretim, fason ve ithalat maliyetini izler; full general-ledger manufacturing costing platformu kurmaz.

Maliyet authoritative stock/purchase/import source hareketlerinden türetilir. `products.current_cost` tek başına truth değildir.

## 2. Precision / chronology
High-precision DECIMAL kullanılır. Company base currency ve exchange-rate snapshot saklanır. Silent historical rewrite yoktur.

## 3. Stok maliyet kaynağı
StockMovement gerektiğinde:
- unit cost
- total/base cost
- currency/rate snapshot
- source lineage

taşır.

Transfer destination carrying value'yu taşır; transfer kendi başına kâr/zarar yaratmaz.

## 4. Satınalma maliyeti
GoodsReceipt provisional/known purchase cost ile stock-in yapabilir. SupplierInvoice fiyat farkı gerekiyorsa original receipt/source lineage'a bağlanır.

Aynı quantity/price difference iki kez inventory cost adjustment üretemez.

## 5. Purchase price vs FX difference
Foreign-currency purchase'ta:
- commercial purchase-price difference
- exchange-rate difference
ayrı kavramdır.

Aynı ekonomik fark hem inventory adjustment hem FX effect olarak double-count edilmez.

## 6. Stok sayım valuation
Positive stock count adjustment yeni inventory value yaratır.

Güvenli baseline:
- güvenilir carrying/moving-average cost varsa onu kullan
- güvenilir cost yoksa yetkili explicit valuation/policy olmadan positive value posting yapma
- negative adjustment mevcut carrying-value policy ile çıkar

Silent zero-cost positive stock creation yoktur.

## 7. Üretim maliyeti
Basit üretim:
`çıkan gerçek malzeme maliyeti + explicit üretim/fason ek giderleri = mamule dağıtılabilir maliyet`.

Kısmi mamul girişi varsa posted output quantity'ye deterministic basis ile maliyet dağıtılır. Unresolved material/output quantity ile production close olmaz.

Routing/work-center/labor-machine standard rate/OEE/ECO costing core değildir.

## 8. Fason maliyeti
Company-owned gönderilen malzeme carrying value custody'de korunur. Fason hizmet bedeli ve explicit diğer giderler gelen mamul maliyetine uygun basis ile eklenebilir.

Fire/eksik ayrı adjustment/cost effect'tir; aynı value mamule ikinci kez yüklenmez.

## 9. İthalat / landed cost
Cost items container ve/veya shipment/general seviyede tutulur.

Allocation basis snapshot:
- purchase value
- quantity
- weight
- volume
- manual rate

Source total ile allocated total reconcile edilir. Rounding artığı deterministic dağıtılır.

## 10. Late landed cost
Geç gelen navlun/vergi/hizmet vb. cost original import/receipt lineage'a bağlanır.

Cost yalnız current on-hand'a körlemesine yığılmaz. Gerekirse:
- consumed share
- on-hand share
ayrımı policy ile yapılır.

Bu policy `19_ACIK_KARARLAR.md` A-17 kapanınca locked hale gelir.

## 11. Returns
Satış iadesinde mümkünse original outbound cost lineage kullanılır. Alış iadesi original receipt/cost lineage üzerinden value çıkarır.

## 12. Kârlılık
Sales profitability gelir ile seçilen cost snapshot/projection'ı karşılaştırır. Geçmiş rapor current master cost değişince sessizce anlam değiştirmez.

## 13. Marketplace/B2B contribution
Commission/shipping/return/provider fee ancak gerçek provider evidence varsa actual gösterilir. Tahmini değer actual truth gibi sunulmaz.

## 14. Projection / rebuild
Cost balance/reporting projection authority değildir. Incremental/full rebuild deterministic aynı sonucu üretmelidir. Mismatch silent fix değil alert → investigate → controlled correction akışı kullanır.

## 15. Audit / versioning
Cost allocation run:
- kim
- ne zaman
- yöntem
- source total
- allocated total
- rounding difference
- source refs
ile saklanır. Finalized run silent mutate edilmez; version/reversal/correction kullanılır.

## 16. Scope dışı
- full standard-cost/MRP variance platformu
- routing/work-center costing
- OEE cost accounting
- ECO rebase costing
- plant maintenance capitalization
- generic manufacturing costing suite
V1 core değildir.
