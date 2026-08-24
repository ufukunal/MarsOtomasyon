# 23 — Read Models, Projeksiyonlar ve Arama V4.1

## 1. İlke
Transactional ledger/document kayıtları authority'dir; UI ve raporlar gerektiğinde rebuildable read model/projection kullanabilir. Generic CQRS framework kurulmaz.

## 2. Authoritative sources
- `account_transactions`
- `stock_movements`
- `treasury_movements`
- tahsilat/ödeme/POS/çek-senet source records + effect refs
- finalized sales/purchase documents
- goods receipt / supplier invoice lineage
- production material issue / finished good receipt
- transfer/subcontract custody lineage
- import cost allocations
- marketplace settlement/payout source evidence

OpenItem settlement authority yoktur.

## 3. Cari projectionları
- signed balance in Account book currency
- base-currency analytical equivalent where needed
- debit/credit movement summary
- period movement
- statement running balance
- risk/exposure
- due-date/aging analytical view

Aging invoice due-date'den analiz edilebilir; hangi ödeme hangi faturayı kapattı allocation'ı yapılmaz. Farklı raw currency bakiyeleri tek tutar olarak toplanmaz.

## 4. Stok projectionları
- physical
- reserved
- quarantine/blocked
- available
- warehouse/location balance
- in-transit transfer custody
- subcontract custody
- movement summary
- count difference
- moving-average carrying cost/value

Critical stock guard stale async projection'a kör dayanmaz.

## 5. Sales / Purchase projectionları
- sales ordered/net-dispatched/net-invoiced/cancelled/returned/remaining
- purchase ordered/accepted/invoiced/remaining
- goods receipt accepted/pending/rejected
- open quantity commitments
- daily/monthly sales/purchase
- return summary

Reversal net counters projection'da original + reversal source'lardan yeniden üretilebilir.

## 6. Finance / Treasury projectionları
- cash balance
- bank balance
- POS pending/settled/chargeback
- expense summary
- cheque/note portfolio
- endorsed instruments
- statement match status
- cash count differences

Balance `treasury_movements` üzerinden rebuild edilir; source table total'i authority değildir.

## 7. Marketplace clearing projectionları
Channel/account bazında:
- opening clearing
- invoiced receivable
- refund/return
- commission/service/shipping/adjustment
- payout
- closing clearing
- unreconciled provider settlement rows
- last settlement/payout watermark

gösterilebilir.

Actual fee/contribution yalnız provider evidence varsa actual olarak işaretlenir.

## 8. Production / Fason
- production order status
- planned/issued material
- finished goods receipt
- fire/missing
- fason sent/received/remaining custody quantity/value

OperationRun/work-center/OEE/QCP projection core değildir.

## 9. E-Ticaret / B2B
- channel order summary
- sync/problem counts
- product mapping status
- media publish status where supported
- stock/price publish summary
- return/question summary
- B2B Account/order link
- channel contribution only when real provider evidence exists

## 10. Dashboard
Dashboard projection/read service olabilir; business authority değildir. KPI as-of timestamp ve source consistency gerektiğinde gösterilir.

## 11. Rapor Merkezi
Hazır raporlar `13_UI_UX_RAPORLAMA.md` içindeki stabil report_key kataloğunu kullanır. SavedReport yalnız filter/view preference'tır. ScheduledReport runtime permission/company context ile yeniden hesaplanır.

## 12. PostgreSQL Search
İlk sürüm:
- `tsvector/tsquery`
- `pg_trgm`
- measured need'e göre GIN/GiST

Arama örnekleri:
- Cari: kod, unvan, yetkili, telefon, e-posta, vergi no
- Ürün: kod, barkod, ad, kategori, marka, kısa teknik alan
- Belge: belge no, cari/customer snapshot, referans
- B2B/E-Ticaret: ürün katalog araması

Exact/prefix SKU/barcode/document no sonuçları fuzzy text'ten yüksek öncelik alır.

## 13. Türkçe normalization
`I/İ/ı/i`, case-folding ve trigram davranışı gerçek Türkçe testlerle doğrulanır. Search normalization display değerini bozmaz.

## 14. Search authority sınırı
Search sonucu fiyat/stok/cari bakiye authority değildir ve authorization bypass edemez. Result ID gerçek entity/read model fetch sırasında permission + company scope ile doğrulanır.

## 15. Eventual consistency sınırı
Finans/stok kritik işlemden sonra kullanıcıya doğru sonuç hemen gerekiyorsa:
- authoritative query
- veya aynı transaction'da updated projection
kullanılır.

Eventual consistency kullanıcıyı yanlış business kararına yönlendirecek yerde kullanılmaz.

## 16. Rebuild
Projection authoritative source'tan yeniden üretilebilir olmalıdır. Delete/rebuild business history kaybettirmez. Rebuild mismatch silent ledger adjustment yapmaz.

## 17. Integrity checks
Periyodik/diagnostic reconciliation adayları:
- account_transactions ↔ cari balance + currency
- stock_movements ↔ physical/available/value
- transfer issue ↔ in-transit ↔ destination receipt
- subcontract custody ↔ sent/received/fire/remaining
- reservation ↔ physical available
- sales ordered/net-dispatched/net-invoiced/returned/remaining
- purchase ordered/accepted/pending/rejected/invoiced/remaining
- treasury_movements ↔ cash/bank/POS balances
- instrument lifecycle ↔ customer/supplier cari effects
- statement row ↔ matched/created treasury movement
- ecommerce external identity ↔ Mars order uniqueness
- marketplace settlement rows ↔ clearing/treasury effects
- import allocation ↔ source cost total

## 18. Cache
Valkey projection/search/business truth değildir. Cache temizlenerek source/read modelden doğru veri yeniden üretilebilir.

## 19. Performans
İlk aşama normal PostgreSQL query/read services'tir. Ölçüm sonrası materialized view/summary table/dedicated projection eklenebilir. Tahmine dayalı CQRS/search cluster kurulmaz.
