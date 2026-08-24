# 24 — Bulk Import / Export Güvenliği V4

## 1. İlke
CSV/Excel/MT940/API bulk import business validation'ı bypass etmez. Akış:
`Upload → Parse → Map → Normalize → DTO → Validate → Preview/Dry-run → Chunk → Business Use Case → Result`.

## 2. Yasak pattern
- raw uncontrolled `Model::create($row)` ile business rule bypass
- arbitrary mass assignment
- user-provided table/class/SQL/expression execution
- yüzlerce business entity'yi tek dev transaction'a alma
- finalized ledger/document silent overwrite

## 3. Import schema / allow-list
Her import type explicit schema taşır:
- accepted columns
- type/scale
- required/optional
- enum/reference mapping
- row/file limits
- unknown-column reject/ignore policy

Dynamic mapping yalnız allow-listed target alanlara yapılır.

## 4. Güvenlik
- permission + company scope server-side fixed
- MIME/extension/content sniff
- file/row/decompression limits
- formula/CSV injection defense
- randomized non-executable temp/storage
- checksum
- parser time/memory limits
- risk bazlı malware scan
- temp lifecycle cleanup

## 5. Preview / dry-run
Commit öncesi kullanıcıya:
- valid rows
- invalid rows
- row errors
- duplicate warnings
- reference mapping issues
- calculated stock/finance/business effect where practical
- totals/reconciliation
sunulur.

Finance/stock import daha katı preview gerektirir.

## 6. Validasyon
Satır bazında:
- required fields
- format/precision
- reference/master match
- unique/duplicate
- authorization/company
- business invariant
- quantity/amount caps

Hatalar export edilebilir result file/manifest ile verilir.

## 7. Chunking / transaction
Küçük güvenli import tek transaction olabilir. Büyük import:
- chunked
- restartable
- her row/entity veya güvenli küçük group uygun transaction
- partial commit semantics explicit
- processed/error status manifest
kullanır.

Tek hatalı satır diğer doğruları gereksiz rollback ettirmez; ancak aynı business aggregate'ın atomicity sınırı bölünmez.

## 8. Restart / idempotency
- import batch identity
- stable source/row/client-operation identity where required
- processed/error status
- retry-safe processing
- source-effect uniqueness

Aynı file hash tek başına her business senaryoda yeterli dedupe değildir.

## 9. Duplicate handling
Deterministic identity önceliklidir. Fuzzy match yalnız suggestion'dır; cari/ürün/finans auto-merge yoktur.

## 10. Cari import
Cari master/opening/account transaction import company scope + signed balance reconciliation kullanır. OpenItem/settlement target authority değildir.

## 11. Ürün import
Target tek satış + tek alış fiyatı taşır. Import çoklu fiyat kolonları içerirse canonical sale/purchase mapping açıkça seçilir.

Lot/seri input varsa V1 target'a sessiz authority olarak eklenmez; migration/import policy ile raporlanır.

## 12. Stok import
Opening/adjustment normal StockMovement use-case'i üzerinden gider. Quantity, warehouse/location, cost, source identity validate edilir.

## 13. Banka ekstresi import
Akış:
`Dosya Seç → Önizleme → Eşleştirme → İçe Aktar`.

Formatlar:
- Excel
- CSV
- MT940

Statement row stable identity/fingerprint internal duplicate guard olarak kullanılabilir; kullanıcıya teknik fingerprint gösterilmez.

## 14. Marketplace bulk
External entity identity provider-account scoped tutulur. External order identity ile provider message/event identity ayrıdır. Duplicate event ikinci Mars entity/effect üretmez.

## 15. Bulk fiyat güncelleme
Generic çoklu price-list wizard yoktur. Tek satış/alış fiyatı toplu değişecekse:
`Filter/Select → Formula/Value → Preview Old/New → Rounding → Confirm → Product/Pricing Business Action`.

Raw table update yoktur.

## 16. Export
Small export synchronous olabilir. Büyük export:
`Export Request → Job → chunk/stream → storage → ready notification → expiring authorized link`.

Formatlar ihtiyaca göre CSV/XLSX/JSON/PDF.

## 17. Export governance
- permission/company scope
- sensitive field masking/removal
- filter snapshot
- user/request metadata
- artifact expiry/retention
- download audit where needed

Scheduled export runtime authorization'ı tekrar kontrol eder.

## 18. Rapor export
Rapor Merkezi exportu ready report definition + current filters üzerinden çalışır. User-defined raw SQL export yoktur.

## 19. API bulk
Gerçek ihtiyaç varsa batch resource + item-level result kullanılır. HTTP request boyunca tek dev transaction bekletilmez.

## 20. Audit
Kim, ne zaman, hangi import/export type, file hash/reference, row counts, success/error counts ve output artifact bilgisi audit edilir. Raw sensitive payload gereksiz yere audit/log'a yazılmaz.
