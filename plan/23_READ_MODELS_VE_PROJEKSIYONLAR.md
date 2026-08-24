# 23 — Read Models, Projeksiyonlar ve Arama V4

## 1. İlke
Transactional ledger/document kayıtları authority'dir; UI ve raporlar gerektiğinde rebuildable read model/projection kullanabilir. Generic CQRS framework kurulmaz.

## 2. Authoritative sources
- `account_transactions`
- `stock_movements`
- tahsilat/ödeme/POS/çek-senet source records
- finalized sales/purchase documents
- goods receipt / supplier invoice lineage
- production material issue / finished good receipt
- fason custody
- import cost allocations
- provider evidence where actual

OpenItem settlement authority yoktur.

## 3. Cari projectionları
- signed balance
- debit/credit movement summary
- period movement
- statement running balance
- risk/exposure
- due-date/aging analytical view

Aging invoice due-date'den analiz edilebilir; hangi ödeme hangi faturayı kapattı allocation'ı yapılmaz.

## 4. Stok projectionları
- physical
- reserved
- quarantine/blocked if used
- available
- warehouse/location balance
- movement summary
- count difference

Critical stock guard stale async projection'a kör dayanmaz.

## 5. Sales / Purchase projectionları
- sales ordered/dispatched/invoiced/remaining
- purchase ordered/received/invoiced/remaining
- open quantity commitments
- daily/monthly sales/purchase
- return summary

## 6. Finance projectionları
- cash balance
- bank balance
- POS pending/settled
- expense summary
- cheque/note portfolio
- statement match status
- cash count differences

## 7. Production / Fason
- production order status
- planned/issued material
- finished goods receipt
- fire/missing
- fason sent/received/remaining

OperationRun/work-center/OEE/QCP projection core değildir.

## 8. E-Ticaret / B2B
- channel order summary
- sync/problem counts
- product mapping status
- stock publish summary
- return/question summary
- B2B Account/order link
- channel contribution only when real provider evidence exists

## 9. Dashboard
Dashboard projection/read service olabilir; business authority değildir. KPI as-of timestamp ve source consistency gerektiğinde gösterilir.

## 10. Rapor Merkezi
Hazır raporlar read model veya güvenli parameterized query kullanır. SavedReport yalnız filter/view preference'tır. ScheduledReport runtime permission/company context ile yeniden hesaplanır.

## 11. PostgreSQL Search
İlk sürüm:
- `tsvector/tsquery`
- `pg_trgm`
- measured need'e göre GIN/GiST

Arama örnekleri:
- Cari: kod, unvan, yetkili, telefon, e-posta, vergi no
- Ürün: kod, barkod, ad, kategori, marka, kısa teknik alan
- Belge: belge no, cari, referans
- B2B/E-Ticaret: ürün katalog araması

Exact/prefix SKU/barcode/document no sonuçları fuzzy text'ten yüksek öncelik alır.

## 12. Türkçe normalization
`I/İ/ı/i`, case-folding ve trigram davranışı gerçek Türkçe testlerle doğrulanır. Search normalization display değerini bozmaz.

## 13. Search authority sınırı
Search sonucu:
- fiyat authority değildir
- stok authority değildir
- cari bakiye authority değildir
- authorization bypass edemez

Result ID üzerinden gerçek entity/read model fetch server-side permission + company scope ile yapılır.

## 14. Eventual consistency sınırı
Finans/stok kritik işlemden sonra kullanıcıya doğru sonuç hemen gerekiyorsa:
- authoritative query
- veya aynı transaction'da updated projection
kullanılır.

Eventual consistency kullanıcıyı yanlış business kararına yönlendirecek yerde kullanılmaz.

## 15. Rebuild
Projection authoritative source'tan yeniden üretilebilir olmalıdır. Delete/rebuild business history kaybettirmez. Rebuild mismatch silent ledger adjustment yapmaz.

## 16. Integrity checks
Periyodik/diagnostic reconciliation adayları:
- account_transactions ↔ cari balance
- stock_movements ↔ stock balance
- reservation ↔ remaining fulfillment
- sales ordered/dispatched/invoiced/remaining
- purchase ordered/received/invoiced/remaining
- cash/bank movements ↔ balances
- instrument lifecycle ↔ cari effects
- statement row ↔ matched/created movement
- ecommerce external identity ↔ Mars order uniqueness
- import allocation ↔ source cost total

## 17. Cache
Valkey projection/search/business truth değildir. Cache temizlenerek source/read modelden doğru veri yeniden üretilebilir.

## 18. Performans
İlk aşama normal PostgreSQL query/read services'tir. Ölçüm sonrası:
- materialized view
- summary table
- dedicated projection
eklenebilir. Tahmine dayalı CQRS/search cluster kurulmaz.
