# 20 — Veri Migrasyonu ve Go-Live V4.1

## 1. İlke
`MarsEski` application code taşınmaz. Gerekli master/transaction geçmişi kontrollü, repeatable, reconciled ve side-effect-safe ETL ile yeni PostgreSQL modeline aktarılır.

## 2. Ana akış
`Extract → Staging → Normalize → Validate → Duplicate Suggestions → Review → Reconcile → Dry Run → Freeze/Cutover → Final Import → Delta Catch-up → Post-Go-Live Reconciliation`.

## 3. Source identity
Her mantıksal legacy kurulum için stabil `source_instance_id` kullanılır. Her export ayrıca `export_snapshot_id`, checksum, extracted_at ve cutoff metadata taşır.

Default dedupe identity:
`company + source_instance + entity_type + source_id`
veya domain equivalent.

Batch/export ID tek başına business idempotency identity değildir.

## 4. Mapping / normalization
Deterministic mapping yapılır:
- company/branch
- cari + book currency
- ürün/SKU/barkod
- birim
- currency/rate
- tax/tax-zero reason
- tarih/posting date
- belge durumu
- external refs

Fuzzy matching yalnız öneri üretir; cari/ürün/finans auto-merge yapmaz.

## 5. Cari migration
Target bakiye bazlıdır ve V1 Account tek book currency taşır.

Legacy OpenItem/invoice allocation varsa settlement authority olarak taşınmaz.

Seçenekler:
- güvenilir transaction history → historical `account_transactions`
- yalnız bakiye güvenilir → controlled opening AccountTransaction
- history + opening aynı ekonomik bakiyeyi iki kez üretmez

Reconciliation target: signed cari balance **Account book currency** bazında. Farklı currency legacy hareketleri target Account mapping/review olmadan tek bakiyeye toplanmaz.

## 6. Treasury migration
Cash/Bank/POS authority `treasury_movements` olduğundan opening/history import source identity ile bu ledger'a controlled effect üretir.

Reconcile:
- cash account balance
- bank account balance
- POS pending/settled where imported
- virman pair integrity

## 7. Stok migration
Target authority `stock_movements`; valuation moving weighted average'dır.

Legacy lot/serial varsa V1 target'ta aggregation/mapping review gerekir; sessiz veri kaybı yapılmaz. Opening/history miktarları warehouse/location seviyesinde, değerleri company+product carrying value seviyesinde reconcile edilir.

In-transit veya fason custody açık kalemleri varsa source/target custody mapping ayrıca yapılır; şirket value'su kaybolmaz.

## 8. Fiyat migration
Target ürün başına tek net satış + tek net alış fiyatıdır. Legacy çoklu fiyat listeleri varsa canonical sale/purchase mapping açık artifact olarak seçilir; sessiz rastgele seçim yoktur.

## 9. Historical writer
Historical import:
- same-company
- decimal precision
- totals/tax snapshot
- source lineage
- source-effect idempotency
- posting/history chronology
kurallarını korur.

Current-time approval/notification/provider workflow'unu körlemesine çalıştırmaz. `imported/historical` metadata explicit olabilir.

## 10. Historical side-effect suppression
Migration sırasında:
- SMS/e-posta/WhatsApp gönderilmez
- E-Belge submit edilmez
- marketplace stock/price/media/order publish edilmez
- outbound webhook gönderilmez
- shipping/payment provider çağrılmaz

Dry-run ve final import bu kuralı test eder.

## 11. Import sırası
Öneri:
`Company/settings → users/identity gerekirse → cariler → product/catalog → warehouses → opening/history stock/cost → commercial documents → account transactions/opening balances → treasury/cash/bank → checks/notes → production/fason/import artifacts → external mappings/files → marketplace clearing openings`.

Dependency bulunmayan tarihsel veri read-only archive'da kalabilir.

## 12. Marketplace customer / clearing migration
Legacy marketplace sipariş customer bilgisi legal/order snapshot olarak taşınabilir; her customer için Account yaratılması zorunlu değildir.

Aktif marketplace account için:
- channel/account mapping
- Marketplace Clearing Account
- açık receivable/refund/adjustment
- provider payout/settlement watermark
- external settlement identities
reconcile edilir.

Cutover opening clearing balance provider evidence veya controlled opening AccountTransaction ile açıklanabilir olmalıdır.

## 13. Reconciliation gates
Go-live öncesi en az:
- cari signed balance + currency
- stock qty/value + in-transit/subcontract custody
- cash/bank/POS balance
- open sales/purchase quantity progress
- cheque/note portfolio + endorsement effects
- numbering/sequence
- external channel mappings
- marketplace clearing/payout state
- duplicate source identity
- file coverage/checksum
reconcile edilir.

OpenItem settlement reconciliation gate değildir.

## 14. Dry-run
Dry-run sonucu:
- süre
- row/entity counts
- valid/error/duplicate counts
- restart/idempotency sonucu
- cari/stok/value/cash/bank reconciliation
- marketplace clearing reconciliation
- file/checksum coverage
- external side-effect count = 0
- unresolved mappings
üretir.

Aynı logical source rehearsal/final arasında aynı `source_instance_id` kullanır.

## 15. External channel cutover — TÜM ENABLED KANALLAR
Cutover yalnız WooCommerce/Trendyol ile sınırlı değildir. Go-live anında **enabled/active olan her external channel/account** için aşağıdaki stratejilerden biri açıkça seçilir:
- provider polling cursor/watermark freeze/capture,
- webhook durable Inbox buffering,
- provider/account temporary pause/kill-switch,
- veya provider contract'a uygun eşdeğer delta strategy.

Her enabled channel için kayıt:
- last imported order/event cursor
- pending Inbox
- pending/ambiguous Outbox
- desired stock/price/media state
- open returns/claims
- settlement/payout watermark where supported
- connection/capability status

tutulur ve reconcile edilir.

Deferred/disabled provider cutover zorunluluğu yaratmaz.

## 16. Cutover
Kaynak sistem write-freeze veya kontrollü delta penceresi kullanır. Son extraction/import sonrası reconciliation tamamlanmadan yeni sistem authoritative ilan edilmez.

Yeni kullanıcı write açılmadan önce:
- sequence
- ledger reconciliation
- enabled-channel delta backlog
- desired marketplace state
- clearing opening
kontrol edilir.

## 17. Point of no return / rollback
Mars production business write kabul etmeden abort mümkündür. Yeni production writes başladıktan sonra default yaklaşım roll-forward + reconciliation'dır; eski sisteme kör rollback varsayılmaz.

## 18. Backup / restore
Go-live öncesi:
- production-like backup
- isolated restore drill
- manifest/checksum/version
- schema migration compatibility
- critical smoke
başarılı olmalıdır.

## 19. Initial integration reconciliation
Her enabled active channel/account için:
- provider/channel mappings
- customer snapshot/clearing mapping
- desired stock/price/media
- imported e-document `do_not_submit` veya equivalent suppression
- pending real operations
- settlement/payout watermark if supported
- credential connection tests
- capability matrix
kontrol edilir.

## 20. Go-live smoke
En az:
- login/authorization/company context
- B2B auth smoke if enabled
- cari create/read
- ürün search
- sales order
- dispatch stock OUT
- invoice posting
- collection
- goods receipt accepted/pending/rejected
- supplier invoice/payment
- cash/bank/treasury movement
- stock movement + moving-average cost
- instrument endorsement/settlement smoke where relevant
- report totals
- queue/scheduler/outbox
- enabled marketplace order + desired stock sync smoke
- marketplace clearing posting smoke where enabled
- critical V16.3 routes

## 21. Post-go-live monitoring
- cari integrity
- stock qty/value/custody integrity
- cash/bank/POS
- instrument lifecycle
- integration backlog
- marketplace clearing mismatch
- duplicate/gap
- numbering
- search health
- controlled correction count
izlenir.

## 22. Arşiv
MarsEski repo/veri exportları read-only arşiv olarak saklanabilir. Yeni production runtime'ın legacy sisteme dependency'si yoktur.
