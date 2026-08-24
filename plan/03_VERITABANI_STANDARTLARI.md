# 03 — Veritabanı Standartları V4.1

## 1. Motor
Production ve CI transactional DB yalnız **PostgreSQL 18**. SQLite/MySQL/MariaDB davranışına göre schema tasarlanmaz.

## 2. PostgreSQL-first, Laravel-first
Schema Builder/Eloquent/Query Builder önceliklidir. PostgreSQL JSONB/index/constraint/FTS özellikleri gerçek ihtiyaçta kullanılır. Business truth stored procedure/trigger içine gizlenmez.

## 3. Kimlikler
- Internal PK tablo ailesi içinde tutarlı bigint/identity veya gerekçeli UUID/ULID.
- External/public ID gerekiyorsa DB PK'den ayrıdır.
- Kullanıcı-visible belge no primary key değildir.
- Company-scoped business unique key gerektiğinde `company_id` içerir.

## 4. Company / Branch
- Tenant kayıt `company_id` taşır.
- Referanslanan tenant entity aynı company olmalıdır.
- Branch yalnız business anlamı olan kayıtta bulunur; her tabloya mekanik eklenmez.

## 5. Para, miktar, maliyet, kur
Varsayılanlar:
- money/qty/cost: `NUMERIC(20,6)`
- exchange rate: `NUMERIC(20,10)`
- finansal source-of-truth'ta FLOAT/DOUBLE yoktur.

Currency minor-unit ve rounding policy explicit uygulanır. Miktar ürün/birim hassasiyetine göre doğrulanır.

## 6. Account currency
V1 Account tek `book_currency` taşır. `account_transactions.currency_code` Account book currency ile aynı olmalıdır. Gerekirse company base amount/rate snapshot ayrıca tutulur. Farklı raw currency amount'ları tek signed balance'a eklenmez.

## 7. Para birimi snapshot
İşlem gerektiğinde:
- `currency_code`
- `foreign_amount`
- `exchange_rate`
- `local/base_amount`

tutar. Sonraki kur değişikliği geçmiş belgeyi değiştirmez. Company base currency ve business timezone explicit ayardır.

## 8. Zaman ve posting kavramları
Persistent instant timezone-aware (`timestamptz`) tutulur. Aşağıdakiler ayrı kavramlardır:
- `document_date`
- `posting_date`
- `posted_at`
- `created_at`
- `due_date`

PostingPeriod `posting_date` üzerinden doğrulanır. Vade cari settlement allocation authority değildir; bilgi/rapor alanıdır.

## 9. Immutable snapshot / lineage
Belge posting olduğunda değişebilir master verilerden gerekli alanlar snapshot alınır:
- cari/customer legal snapshot
- ürün adı/kodu
- entered/net fiyat
- iskonto/KDV/tax-zero reason
- entered price mode
- kur
- source document/line lineage

Geçmiş belge current master değişince silent rewrite edilmez.

## 10. Ledger tabloları
### `account_transactions`
Cari/clearing finans authority. Her kayıt source type/id, correlation, currency ve gerektiğinde reversal bağlantısı taşır. Normal CRUD ile update/delete edilmez.

### `stock_movements`
Stok authority. Warehouse/location/product/quantity/direction/source lineage/carrying-cost ile fiziksel hareketi temsil eder. Reservation stock movement değildir.

### `treasury_movements`
Kasa/banka/POS parasal bakiye authority. Collection/Payment/Expense/Transfer/POSSettlement/CashCount gibi source kayıtlar deterministic source-effect üretir. Normal CRUD balance update yoktur.

Ledger hareketleri append/reversal/correction modeliyle çalışır.

## 11. Moving weighted average cost
V1 company + product carrying cost moving weighted average ile hesaplanır. StockMovement unit/total cost snapshot taşıyabilir.

- inbound average'ı deterministik günceller
- outbound current carrying value ile çıkar
- transfer carrying value'yu in-transit → destination taşır
- positive count adjustment cost yoksa explicit valuation zorunludur
- zero-cost positive inventory varsayılan yoktur

## 12. Progress alanları
Sales/Purchase satırlarında ordered/dispatched/accepted/invoiced/cancelled/returned ve reversal-safe net/remaining türevleri business invariant ve DB defense ile korunur. Negatif remaining yoktur.

## 13. Belge numarası
Firma + belge tipi + yıl/dönem ve gerekiyorsa şube kapsamındaki sequence row transaction içinde kilitlenir. Draft/reference/public ID legal number değildir. Legal/final number posting/finalization aşamasında verilebilir. UNIQUE constraint son savunmadır.

## 14. Tax / price snapshot
Core Product `sale_price_net/purchase_price_net` tutar. Document line/header gerekirse `price_input_mode` (`exclusive|inclusive`), entered value, net value, discount allocation, tax base, tax rate/amount, `tax_zero_reason_code` ve rounding snapshot taşır.

## 15. Transaction isolation / locking
Baseline **READ COMMITTED**.

Critical check-then-act işlemler explicit row lock, atomic update ve/veya unique constraint kullanır. `SERIALIZABLE` yalnız gerekçeli dar use-case'te kullanılır.

Lock adayları:
- document posting/reversal
- sequence
- reservation/stock availability
- order quantity progress
- moving-average cost update
- stock/cash count finalization
- bank reconciliation
- cheque/note lifecycle/settlement/endorsement
- marketplace settlement source-effect
- source-effect creation

Lock ordering deterministic olmalıdır.

## 16. Constraint ilkesi
Application validation tek başına yeterli değildir. Uygun yerde:
- NOT NULL
- CHECK
- UNIQUE
- FOREIGN KEY
- partial unique index
- exclusion/locking strategy
kullanılır.

Örnek kritik invariantlar:
- quality split toplamı physical_received ile eşleşir
- aynı source/effect ikinci ledger hareketi üretmez
- provider/account + external settlement identity unique
- transfer issue/receipt line total caps

## 17. Search
İlk sürüm PostgreSQL FTS + `pg_trgm` kullanır. Cari/ürün/barkod/belge/B2B aramaları uygun GIN/GiST indexlerle desteklenir.

Canonical textual identity gereken alanlarda raw/display ile normalized değer ayrılabilir. Türkçe `I/İ/ı/i` normalization test edilir.

Search index authority değildir; entity fetch authorization/company scope'u tekrar doğrular.

## 18. JSONB
Provider payload, annotation geometry ve gerçekten değişken metadata için kullanılabilir. Para, miktar, vergi, bakiye, stok/treasury movement, state veya kritik source lineage opaque JSON içine gömülmez.

## 19. Ürün fiyatı ve lot/seri
Core:
- tek net satış fiyatı
- tek net alış fiyatı
- lot/seri schema/UI yok

B2B ayrı price-list truth'u tutmaz; Cari İskontosu kullanır.

## 20. Files / medya
Binary Laravel Filesystem/storage abstraction'da; DB metadata, checksum, version, visibility, path/provider tutar.

Ürün görselleri kullanım yeri/kanal/site kimliğiyle ilişkilendirilebilir; her destination bağımsız ana görsel/galeri/sıra ve provider publish metadata taşıyabilir.

## 21. API credential storage
Provider credential kaydı provider/channel/account scope + encrypted secret fields + `last_test_at/status` metadata taşır. Gerçek secret UI/API read response'ta geri verilmez.

## 22. Projection
Balance/reporting/read-model tabloları authority değildir; rebuild/reconciliation yapılabilir.

Adaylar:
- cari bakiye
- stok bakiye/kullanılabilir/in-transit
- moving-average cost summary
- satış ve satınalma progress
- cash/bank/POS summary
- marketplace clearing/settlement summary
- kanal sync summary
- rapor KPI

## 23. Audit
Created/updated actor gerektiği domainlerde tutulur. Güvenlik/business audit append-oriented ayrı kayıttır; ledger yerine geçmez.

## 24. Schema evolution
Destructive değişiklikler `25_MIGRATION_VE_SCHEMA_DEGISIKLIK_PLAYBOOK.md` ile:
`expand → compatible code → chunked/restartable backfill → verify/reconcile → switch → later contract`.
