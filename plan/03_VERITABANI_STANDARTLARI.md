# 03 — Veritabanı Standartları V4

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

## 6. Para birimi snapshot
İşlem gerektiğinde:
- `currency_code`
- `foreign_amount`
- `exchange_rate`
- `local/base_amount`

tutar. Sonraki kur değişikliği geçmiş belgeyi değiştirmez. Company base currency ve business timezone explicit ayardır.

## 7. Zaman ve posting kavramları
Persistent instant timezone-aware (`timestamptz`) tutulur. Aşağıdakiler ayrı kavramlardır:
- `document_date`
- `posting_date`
- `posted_at`
- `created_at`
- `due_date`

Vade cari settlement allocation authority değildir; bilgi/rapor alanıdır.

## 8. Immutable snapshot / lineage
Belge posting olduğunda değişebilir master verilerden gerekli alanlar snapshot alınır:
- cari unvan/adres/vergi
- ürün adı/kodu
- fiyat/iskonto/KDV
- kur
- source document/line lineage

Geçmiş belge current master değişince silent rewrite edilmez.

## 9. Ledger tabloları
### `account_transactions`
Cari finans authority. Her kayıt source type/id, correlation ve gerektiğinde reversal bağlantısı taşır. Normal CRUD ile update/delete edilmez.

### `stock_movements`
Stok authority. Warehouse/location/product/quantity/direction/source lineage ile fiziksel hareketi temsil eder. Reservation stock movement değildir.

### Treasury
Tahsilat/ödeme/gider/virman/POS/çek-senet source kayıtları cash/bank hareketleriyle deterministic source-effect identity üzerinden ilişkilendirilir.

## 10. Progress alanları
Satış/satınalma satırlarında en az ordered/dispatched/received/invoiced/cancelled/remaining türevleri business invariant ve DB defense ile korunur. Negatif kalan miktar yoktur.

## 11. Belge numarası
Firma + belge tipi + dönem ve gerekiyorsa şube kapsamındaki sequence row transaction içinde kilitlenir. Final/legal number ile draft/reference ID ayrılabilir. UNIQUE constraint son savunmadır.

## 12. Transaction isolation / locking
Baseline **READ COMMITTED**.

Critical check-then-act işlemler explicit row lock, atomic update ve/veya unique constraint kullanır. `SERIALIZABLE` yalnız gerekçeli dar use-case'te kullanılır.

Lock adayları:
- document posting/reversal
- sequence
- reservation/stock availability
- order quantity progress
- stock/cash count finalization
- bank reconciliation
- çek/senet lifecycle/settlement
- source-effect creation

Lock ordering deterministic olmalıdır.

## 13. Constraint ilkesi
Application validation tek başına yeterli değildir. Uygun yerde:
- NOT NULL
- CHECK
- UNIQUE
- FOREIGN KEY
- partial unique index
- exclusion/locking strategy
kullanılır.

## 14. Search
İlk sürüm PostgreSQL FTS + `pg_trgm` kullanır. Cari/ürün/barkod/belge/B2B aramaları uygun GIN/GiST indexlerle desteklenir.

Canonical textual identity gereken alanlarda raw/display ile normalized değer ayrılabilir. Türkçe `I/İ/ı/i` normalization test edilir.

Search index authority değildir; entity fetch authorization/company scope'u tekrar doğrular.

## 15. JSONB
Provider payload, annotation geometry ve gerçekten değişken metadata için kullanılabilir. Para, miktar, vergi, bakiye, stok movement, state veya kritik source lineage opaque JSON içine gömülmez.

## 16. Ürün fiyatı ve lot/seri
Core:
- tek satış fiyatı
- tek alış fiyatı
- lot/seri schema/UI yok

B2B ayrı price-list truth'u tutmaz; Cari İskontosu kullanır.

## 17. Files / medya
Binary Laravel Filesystem/storage abstraction'da; DB metadata, checksum, version, visibility, path/provider tutar.

Ürün görselleri kullanım yeri/kanal/site kimliğiyle ilişkilendirilebilir; her destination bağımsız ana görsel/galeri/sıra taşıyabilir.

## 18. API credential storage
Provider credential kaydı provider/channel/account scope + encrypted secret fields + `last_test_at/status` metadata taşır. Gerçek secret UI/API read response'ta geri verilmez.

## 19. Projection
Balance/reporting/read-model tabloları authority değildir; rebuild/reconciliation yapılabilir.

Adaylar:
- cari bakiye
- stok bakiye/kullanılabilir
- satış ve satınalma progress
- cash/bank summary
- kanal sync summary
- rapor KPI

## 20. Audit
Created/updated actor gerektiği domainlerde tutulur. Güvenlik/business audit append-oriented ayrı kayıttır; ledger yerine geçmez.

## 21. Schema evolution
Destructive değişiklikler `25_MIGRATION_VE_SCHEMA_DEGISIKLIK_PLAYBOOK.md` ile:
`expand → compatible code → chunked/restartable backfill → verify/reconcile → switch → later contract`.
