# 06 — İş Kuralları ve Invariantlar V4.1

Bu dosya MarsOtomasyon'un yanlış finansal/operasyonel sonuç üretmesini engelleyen owner business kurallarını tanımlar.

## 1. Company isolation
Tenant-scoped kayıt active company dışında yazılamaz. Job explicit company context taşır. Referanslanan tenant entity aynı Company olmalıdır.

## 2. Decimal / kur / rounding
Binary float yoktur. High-precision decimal kullanılır. Currency rounding explicit'tir; foreign/base amount gerektiğinde kur snapshot'ıyla saklanır.

## 3. Kesinleşmiş belge değişmezliği
Taslak düzenlenebilir. Kesinleşmiş belge/ledger hareketi silent UPDATE/DELETE edilmez. Düzeltme reversal/adjustment/return/iptal ile yapılır.

## 4. Belge toplamı / vergi / iskonto
Core fiyat net/KDV-hariç normalize edilir. Belge input mode KDV dahil veya hariç olabilir; entered mode snapshot'ta saklanır.

Hesap sırası:
1. line quantity × unit net price
2. line discount
3. document discount'ın line'lara deterministic/oransal allocation'ı
4. taxable base
5. line tax
6. line gross
7. document rounding difference

`gross_total = line_net_after_discount + tax_total + charges_total ± rounding_difference`.

Açıklanamayan fark blocker'dır. Tax line-level hesaplanır; document total line toplamlarından türetilir. Sıfır KDV line `tax_zero_reason_code`/muafiyet gerekçesi taşır.

## 5. Historical snapshot
Posted belge current cari/ürün/fiyat/vergi/kur master'ı değiştiğinde geçmiş değerlerini değiştirmez.

## 6. Cari bakiye
Authority `account_transactions`.
- debit `+`
- credit `-`
- balance = signed sum/rebuildable projection

### Satış
- satış faturası müşteri/financial counterparty bakiyesini artırır
- tahsilat/clearing payout azaltır

### Alış
- alış faturası tedarikçi borcunu artırır
- ödeme azaltır

### Yasak
- invoice bazlı receivable/payable allocation yok
- OpenItem kapatma yok
- `paid/partially_paid` invoice settlement authority değildir

Vade bilgi/rapor alanıdır. Fazla tahsilat/ödeme signed bakiyeyi diğer yöne geçirebilir.

## 7. Cari currency
V1'de her Account tek `book_currency` taşır. AccountTransaction currency bu book currency ile aynı olmalıdır. Company base amount/rate snapshot ayrıca tutulabilir. Farklı raw currency tutarları aynı signed balance içinde toplanmaz.

## 8. Cari görünümü
Signed balance kullanıcıya:
- `Alacaklı` yeşil
- `Borçlu` kırmızı
- zero `Bakiye Yok`
olarak gösterilir.

## 9. Sales quantity / reversal-safe progress
Net counters reversal/correction etkisini düşmüş değerlerdir:
- `net_dispatched = dispatched - reversed_dispatch`
- `net_invoiced = invoiced - reversed_invoice`
- `net_returned = returned - reversed_return`

Formüller:
- `remaining_to_invoice = ordered - cancelled - net_invoiced`
- `remaining_to_dispatch = ordered - cancelled - net_dispatched`
- `cancellable_qty = ordered - cancelled - max(net_dispatched, net_invoiced)`
- `returnable_qty = max(0, eligible_delivered_or_invoiced_qty - net_returned)`

Kurallar:
- over-invoice BLOCK
- over-dispatch BLOCK
- over-cancel BLOCK
- over-return BLOCK
- kısmi işlem normaldir
- remaining < 0 olamaz
- reversal ilgili remaining/progress'i yeniden açabilir

Reservation:
`reserved_qty <= remaining_fulfillable_qty`.
Sevk/cancel/line reduction kullanılmayan reservation'ı release eder.

## 10. Reservation / oversell
Toplam reservation ilgili stok scope'unda `physical - blocked/quarantine` miktarını aşamaz. Negatif stok ve implicit backorder V1'de yoktur. Marketplace order geldiğinde reserve edilemiyorsa business order yaratılabilir fakat fulfilment `Sorun/Stok Eksik` olur; stock movement/negative quantity üretilmez.

## 11. Satış faturası posting
Tek DB transaction:
1. draft/finalize validation
2. posting-period/authorization
3. invoice posted
4. related order net invoiced/remaining update
5. direct-invoice stock policy gerektiriyorsa stock OUT
6. account transaction
7. outbox
8. commit

Bir adım başarısızsa tüm işlem rollback olur. Aynı command retry ikinci etki üretemez.

## 12. Physical stock exactly-once / satış source-effect matrix
Aynı fiziksel miktar iki farklı kaynaktan ikinci kez stock movement üretemez.

- Sipariş → Sevkiyat → Fatura: stock OUT yalnız Sevkiyat posting'de.
- İrsaliyesiz/direct invoice: invoice fiziksel çıkışı temsil ediyorsa stock OUT invoice'da.
- Dispatch stock OUT yaptıysa invoice aynı source quantity için stock effect üretmez.
- GoodsReceipt stock IN yaptıysa SupplierInvoice tekrar stock IN üretmez.

Source document/line + effect type deterministic unique defense taşır.

## 13. Purchasing quantity
`remaining_to_receive = ordered - cancelled - accepted_not_reversed`
`remaining_to_invoice = ordered - cancelled - invoiced_not_reversed`

- over-receipt/acceptance BLOCK
- over-invoice BLOCK
- kısmi mal kabul ve kısmi fatura normaldir

## 14. Goods Receipt quality quantity split
GoodsReceiptLine fiziksel gelen miktarı gerekirse üç quantity'ye böler:
- `accepted_qty`
- `pending_quality_qty`
- `rejected_qty`

Invariant:
`physical_received_qty = accepted + pending + rejected`.

PO `received/remaining_to_receive` progress'i yalnız **accepted_qty** ile kapanır. Pending/rejected fiziksel custody olarak kayda girer fakat available stock değildir. Pending sonradan accepted/rejected transition ile reclassify edilir; aynı physical quantity ikinci stock IN üretmez.

## 15. Inventory
Authority `stock_movements`.
Reservation movement değildir.

Negatif stok V1'de BLOCK. Kullanılabilir stok:
`physical - reserved - quarantine/blocked`.

Kanal publish:
`available - channel_safety_stock`.

## 16. Depo transferi / in-transit custody
Transfer cari/fiyat/KDV belgesi değildir.

Kaynak issue sonrası hedef receipt'e kadar quantity/value şirket varlığı olarak `in_transit` custody'de görünür. Source issue + destination receipt aynı transfer lineage'ında reconcile edilir. Hedef hareketleri duplicate üretilemez. Transfer kendi başına kâr/zarar yaratmaz ve carrying value korunur.

## 17. Stok sayımı
Sistem miktarı, sayılan miktar ve fark tutulur. Finalization exactly-once adjustment üretir. Taslak stok etkisi üretmez. Pozitif adjustment mevcut güvenilir moving-average cost kullanır; yoksa explicit yetkili unit cost olmadan posting yoktur.

## 18. Moving weighted average costing
V1 stock valuation moving weighted average'dır.
- inbound value average'ı deterministik günceller
- outbound current carrying cost ile çıkar
- transfer carrying value'yu taşır
- return mümkünse original source cost kullanır
- silent zero-cost positive inventory yoktur

## 19. Treasury authority
Kasa/banka/POS bakiye authority immutable/appended `treasury_movements` ledger'ıdır. Collection/Payment/Expense/Transfer/POSSettlement source kayıtları deterministic source-effect ile ledger hareketi üretir. Doğrudan cash/bank balance UPDATE edilmez.

## 20. Kasa sayımı
`difference = counted_total - system_balance`.
- denomination quantity toplamı counted total'i üretir
- fark varsa açıklama zorunlu
- taslak bakiye değiştirmez
- tamamlama yalnız bir adjustment effect üretir

## 21. Tahsilat / ödeme türü
Kullanıcı-visible tipler:
`cash | bank | pos | virtual_pos | check | promissory_note | other`.

Type-specific alanlar validation'a tabidir. Hidden type alanları posting authority değildir. Tanımlar kontrollü PaymentMethod/PaymentType config ile genişletilebilir.

## 22. Tahsilat / ödeme atomikliği
Tahsilat/ödeme tek transaction'da:
- source operation
- account transaction
- ilgili treasury/instrument effect
üretir.

Yarım cari/treasury durumu oluşamaz.

## 23. POS
Cari effect = gross tahsilat. Komisyon ayrı expense/treasury effect'tir. POS lifecycle en az `Pending → Settled | Reversed/Chargeback` ayrımını destekler. Net bank settlement cariyi ikinci kez azaltamaz.

## 24. Virman
Same-currency virman kaynak çıkış + hedef giriş atomik çift treasury movement'tır ve toplam değer korunur. Cross-currency için explicit FX/rate/rounding policy gerekir.

## 25. Çek / Senet
### Müşteriden alınan
Teslim/posting anında müşteri bakiyesini azaltır ve portföy kaydı oluşturur. Bankada tahsil ikinci cari effect değildir.

### Tedarikçiye verilen
Teslim/posting anında tedarikçi borcunu azaltır. Bankadan ödeme ikinci cari effect değildir.

### Ciro edilen alınan instrument
Alınan çek/senet tedarikçiye ciro edilirse:
- original customer effect tekrar yazılmaz,
- supplier payable ciro posting anında bir kez azaltılır,
- physical location/holder history güncellenir,
- karşılıksız/iptal durumda hem ilgili supplier effect hem gerekiyorsa original customer effect explicit reversal lineage ile yeniden açılır.

Instrument lifecycle/history silinmez. Aynı instrument aynı ekonomik settlement effect'ini iki kez üretemez.

## 26. Mal kabul kontrolü
Core karar seti:
- `Uygun`
- `Kontrol Bekliyor`
- `Uygun Değil`

Karar quantity split ile uygulanır; tüm satır tek quality state'e zorlanmaz. Generic QMS kurulmaz.

## 27. Pricing / B2B
Her üründe tek net satış + tek net alış fiyatı. B2B fiyatı:
`Product Sale Price - Cari Discount`.
Ayrı B2B discount source yoktur.

## 28. Production
Akış:
`Recipe → Production Order → Material Issue → Finished Good Receipt → Complete`.

- material issue stock OUT
- finished good receipt stock IN
- output/material quantity limitleri aşılmaz
- close öncesi material/output/fire/eksik reconcile edilir
- açıklamasız unresolved quantity ile tamamlama yok

## 29. Fason
Company-owned gönderilen malzeme subcontract custody'de quantity + carrying value ile izlenir. `gelen mamul + fire/eksik + kalan` reconcile edilmeden fason tamamlanmaz. Custody'deki değer company inventory value'dan kaybolmaz.

## 30. İthalat maliyeti ve stok handoff
Container/genel cost allocation selected basis ile source total'e reconcile olmalıdır. Aynı cost item iki kez allocation üretemez. Ürün farklı konteynerlerden gelebilir; lineage korunur.

ImportShipment/Container **kendi başına stock authority değildir**. Fiziksel kabul, linked GoodsReceipt veya kontrollü ImportReceipt use-case'i üzerinden `stock_movements` üretir. Landed-cost posting original receipt/import lineage'a bağlanır.

## 31. Marketplace inbound idempotency
External entity identity ile inbound message identity ayrıdır. Provider-account scoped duplicate event ikinci Sales Order/stock/financial effect üretemez.

## 32. Marketplace financial clearing
Marketplace order/invoice legal customer snapshot taşır; finansal counterparty kanal/account clearing Account olabilir.

Marketplace invoice → clearing receivable.
Marketplace payout → clearing receivable azalması + bank treasury movement.
Komisyon/kargo/hizmet/ceza/chargeback → ayrı fee/expense/settlement effect.

Invariant:
`opening clearing + invoiced/refunded ± provider adjustments - payouts = closing clearing` provider evidence ile reconcile edilebilir olmalıdır. Aynı provider settlement row/fingerprint ikinci effect üretemez.

## 33. E-Ticaret stock publish
`publish_qty = physical - reserved - quarantine/blocked - channel_safety_stock`.
Mars stock authority'dir; provider callback Mars stock truth'unu keyfi overwrite edemez. CURRENT_DESIRED_STATE stale retry eski miktarı geri yazamaz.

## 34. B2B
B2B user/account bir Mars carisine önceden bağlıdır. Siparişte cari seçimi yoktur. B2B order cari bakiyesini etkilemez; invoice effect eder. B2B auth internal User/RBAC'den ayrı context'tir. Risk limit/exposure block/warning policy server-side uygulanır; B2B client bypass edemez.

## 35. Bank statement import
Statement row stable identity/fingerprint ile duplicate guard kullanır. Aynı satır ikinci business movement üretmez.

Kullanıcı-visible durumlar:
- Eşleşti
- Eşleştirme Bekliyor
- Daha Önce Aktarıldı
- Aktarıldı

## 36. Bank reconciliation
Existing bank movement ile match ikinci bank movement yaratmaz. Unmatch/rematch history audit edilir.

## 37. Posting period
Posting/finalization `posting_date` döneminin açık olmasını gerektirir. Closed/frozen period normal kullanıcı için BLOCK. Override ayrı permission + reason + audit ve policy gerekiyorsa approval ister. Backdated document_date period kontrolünü bypass etmez.

## 38. Reports
Rapor/read model authority değildir. Scheduled report çalışma anında authorization/company context tekrar doğrulanır.

## 39. Search
Search result authority değildir. Transactional use-case source tablolara dayanır; search authorization/company scope'u bypass edemez.

## 40. Outbox / retry
Business change + durable Outbox aynı DB transaction'ında. External HTTP transaction dışında. Retry duplicate business effect üretemez. Ambiguous provider result blind resend edilmez; query/reconcile uygulanır.

## 41. Data correction
Production direct SQL/tinker business fix yasaktır. Correction command idempotent, audit'li ve source-lineage'lı olmalıdır.
