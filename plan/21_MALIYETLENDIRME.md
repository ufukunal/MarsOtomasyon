# 21 — Maliyetlendirme V4.1

## 1. Amaç
MarsOtomasyon ürün, alış, üretim, fason ve ithalat maliyetini izler; full general-ledger manufacturing costing platformu kurmaz.

Maliyet authoritative stock/purchase/import source hareketlerinden türetilir. `products.current_cost` tek başına truth değildir.

## 2. V1 valuation yöntemi — LOCKED
V1 perpetual stock valuation yöntemi **moving weighted average**dır.

Scope:
- company + product carrying value
- warehouse/location quantity ayrı izlenebilir fakat transfer company-level average'ı keyfi değiştirmez
- inbound value average'ı deterministik günceller
- outbound o anda geçerli carrying average ile çıkar
- reversal original cost/effect lineage'ı tersine çevirir

FIFO/standard-cost/LIFO V1 core değildir.

## 3. Precision / chronology
High-precision DECIMAL kullanılır. Company base currency ve exchange-rate snapshot saklanır. Silent historical rewrite yoktur.

## 4. StockMovement cost alanları
StockMovement gerektiğinde:
- unit cost
- total/base cost
- currency/rate snapshot
- source lineage
- cost effect/source identity

taşır.

Aynı ekonomik cost effect ikinci kez uygulanamaz.

## 5. Depo transferi / in-transit
Kaynak transfer OUT carrying value'yu in-transit custody'ye taşır. Hedef receipt aynı toplam carrying value ile kabul eder. Transit süre içinde company inventory value kaybolmaz ve transfer P/L yaratmaz.

## 6. Satınalma maliyeti
GoodsReceipt provisional/known purchase cost ile stock-in yapabilir. SupplierInvoice fiyat farkı gerekiyorsa original receipt/source lineage'a bağlanır.

Aynı quantity/price difference iki kez inventory cost adjustment üretemez.

## 7. Purchase price vs FX difference
Foreign-currency purchase'ta:
- commercial purchase-price difference
- exchange-rate difference
ayrı kavramdır.

Aynı ekonomik fark hem inventory adjustment hem FX effect olarak double-count edilmez.

## 8. Stok sayım valuation
Positive stock count adjustment yeni inventory value yaratır.

Policy:
- güvenilir moving-average varsa onu kullan,
- güvenilir cost yoksa yetkili explicit unit cost olmadan positive posting yapma,
- negative adjustment mevcut carrying average ile çıkar.

Silent zero-cost positive stock creation yoktur.

## 9. Sales return / purchase return
Satış iadesinde mümkünse original outbound cost lineage kullanılır; bu yoksa kontrollü moving-average fallback policy kullanılır ve reason/audit taşır.

Alış iadesi original receipt/cost lineage üzerinden value çıkarır; current average'a körlemesine bağlanmaz.

## 10. Üretim maliyeti
Basit üretim:
`çıkan gerçek malzeme carrying cost + explicit üretim/fason ek giderleri = mamule dağıtılabilir maliyet`.

Kısmi mamul girişi varsa posted output quantity'ye deterministic basis ile maliyet dağıtılır. Unresolved material/output quantity ile production close olmaz.

Routing/work-center/labor-machine standard rate/OEE/ECO costing core değildir.

## 11. Fason maliyeti / custody
Company-owned gönderilen malzeme carrying value subcontract custody'de korunur. Gönderim anında value expense olmaz.

Fason hizmet bedeli ve explicit diğer giderler gelen mamul maliyetine uygun basis ile eklenebilir. Fire/eksik ayrı adjustment/cost effect'tir; aynı value mamule ikinci kez yüklenmez.

## 12. İthalat / landed cost
Cost items container ve/veya shipment/general seviyede tutulur.

Allocation basis snapshot:
- purchase value
- quantity
- weight
- volume
- manual rate

Source total ile allocated total reconcile edilir. Rounding artığı deterministic dağıtılır.

## 13. Late landed cost
Geç gelen navlun/vergi/hizmet vb. cost original import/receipt lineage'a bağlanır.

Cost yalnız current on-hand'a körlemesine yığılmaz. Gerekirse:
- consumed share
- on-hand share
ayrımı A-17 landed-cost posting policy ile yapılır.

A-17 `19_ACIK_KARARLAR.md` içinde M16 cost-posting entry gate'idir; moving-average yönteminin kendisi artık açık karar değildir.

## 14. Marketplace/B2B contribution
Commission/shipping/return/provider fee ancak gerçek provider evidence varsa actual gösterilir. Marketplace clearing/settlement fee effect'leri profitability raporunda source evidence ile kullanılır. Tahmini değer actual truth gibi sunulmaz.

## 15. Projection / rebuild
Cost balance/reporting projection authority değildir. Incremental/full rebuild deterministic aynı sonucu üretmelidir. Mismatch silent fix değil alert → investigate → controlled correction akışı kullanır.

## 16. Audit / versioning
Cost allocation/adjustment run:
- kim
- ne zaman
- yöntem
- source total
- allocated total
- rounding difference
- source refs
- reason/version
ile saklanır. Finalized run silent mutate edilmez; version/reversal/correction kullanılır.

## 17. Scope dışı
- FIFO/standard-cost engine V1
- full standard-cost/MRP variance platformu
- routing/work-center costing
- OEE cost accounting
- ECO rebase costing
- plant maintenance capitalization
- generic manufacturing costing suite
V1 core değildir.
