# 20 — Veri Migrasyonu ve Go-Live V4

## 1. İlke
`MarsEski` application code taşınmaz. Gerekli master/transaction geçmişi kontrollü, repeatable, reconciled ve side-effect-safe ETL ile yeni PostgreSQL modeline aktarılır.

## 2. Ana akış
`Extract → Staging → Normalize → Validate → Duplicate Suggestions → Review → Reconcile → Dry Run → Freeze/Cutover → Final Import → Delta Catch-up → Post-Go-Live Reconciliation`.

## 3. Source identity
Her mantıksal legacy kurulum için stabil `source_instance_id` kullanılır. Her export ayrıca:
- `export_snapshot_id`
- checksum
- extracted_at
- cutoff
metadata taşır.

Default dedupe identity:
`company + source_instance + entity_type + source_id`
veya domain equivalent.

Batch/export ID tek başına business idempotency identity değildir.

## 4. Mapping / normalization
Deterministic mapping yapılır:
- company/branch
- cari
- ürün/SKU/barkod
- birim
- currency/rate
- tax
- tarih
- belge durumu
- external refs

Fuzzy matching yalnız öneri üretir; cari/ürün/finans auto-merge yapmaz.

## 5. Cari migration
Target bakiye bazlıdır.

Legacy OpenItem/invoice allocation varsa settlement authority olarak taşınmaz.

Seçenekler:
- güvenilir transaction history → historical `account_transactions`
- yalnız bakiye güvenilir → controlled opening AccountTransaction
- history + opening aynı ekonomik bakiyeyi iki kez üretmez

Reconciliation target: signed cari balance.

## 6. Stok migration
Target authority `stock_movements`.

Legacy lot/serial varsa V1 target'ta aggregation/mapping review gerekir; sessiz veri kaybı yapılmaz. Opening/history miktarları warehouse/location seviyesinde reconcile edilir.

## 7. Fiyat migration
Target ürün başına tek satış + tek alış fiyatıdır. Legacy çoklu fiyat listeleri varsa canonical sale/purchase mapping açık artifact olarak seçilir; sessiz rastgele seçim yoktur.

## 8. Historical writer
Historical import:
- same-company
- decimal precision
- totals
- source lineage
- source-effect idempotency
kurallarını korur.

Current-time approval/notification/provider workflow'unu körlemesine çalıştırmaz. `imported/historical` metadata explicit olabilir.

## 9. Historical side-effect suppression
Migration sırasında:
- SMS/e-posta/WhatsApp gönderilmez
- E-Belge submit edilmez
- marketplace stock/price/order publish edilmez
- outbound webhook gönderilmez
- shipping/payment provider çağrılmaz

Dry-run ve final import bu kuralı test eder.

## 10. Import sırası
Öneri:
`Company/settings → users/identity gerekirse → cariler → product/catalog → warehouses → opening/history stock → commercial documents → account transactions/opening balances → cash/bank → checks/notes → production/fason/import artifacts → external mappings/files`.

Dependency bulunmayan tarihsel veri read-only archive'da kalabilir.

## 11. Reconciliation gates
Go-live öncesi en az:
- cari signed balance
- stock qty/value
- cash balance
- bank balance
- open sales/purchase quantity progress
- cheque/note portfolio
- numbering/sequence
- external channel mappings
- duplicate source identity
- file coverage/checksum
reconcile edilir.

OpenItem settlement reconciliation gate değildir.

## 12. Dry-run
Dry-run sonucu:
- süre
- row/entity counts
- valid/error/duplicate counts
- restart/idempotency sonucu
- cari/stok/cash/bank reconciliation
- file/checksum coverage
- external side-effect count = 0
- unresolved mappings
üretir.

Aynı logical source rehearsal/final arasında aynı `source_instance_id` kullanır.

## 13. External channel cutover
Aktif WooCommerce/Trendyol/B2B için cutover sırasında:
- pause/watermark/cursor
- veya durable Inbox buffering
strategy kullanılır.

Yeni kullanıcı write açılmadan önce delta backlog ve desired stock/price state reconcile edilir.

## 14. Cutover
Kaynak sistem write-freeze veya kontrollü delta penceresi kullanır. Son extraction/import sonrası reconciliation tamamlanmadan yeni sistem authoritative ilan edilmez.

## 15. Point of no return / rollback
Mars production business write kabul etmeden abort mümkündür. Yeni production writes başladıktan sonra default yaklaşım roll-forward + reconciliation'dır; eski sisteme kör rollback varsayılmaz.

## 16. Backup / restore
Go-live öncesi:
- production-like backup
- isolated restore drill
- manifest/checksum/version
- schema migration compatibility
- critical smoke
başarılı olmalıdır.

## 17. Initial integration reconciliation
- provider/channel mappings
- desired stock/price
- imported e-document `do_not_submit` veya equivalent suppression
- cutover sonrası gerçek pending ops
- credential connection tests
kontrol edilir.

## 18. Go-live smoke
En az:
- login/authorization/company context
- cari create/read
- ürün search
- sales order
- invoice posting
- collection
- goods receipt
- supplier invoice/payment
- cash/bank movement
- stock movement
- report totals
- queue/scheduler/outbox
- critical V16.3 routes

## 19. Post-go-live monitoring
- cari integrity
- stock integrity
- cash/bank
- instrument lifecycle
- integration backlog
- duplicate/gap
- numbering
- search health
- controlled correction count
izlenir.

## 20. Arşiv
MarsEski repo/veri exportları read-only arşiv olarak saklanabilir. Yeni production runtime'ın legacy sisteme dependency'si yoktur.
