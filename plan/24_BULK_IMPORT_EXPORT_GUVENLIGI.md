# 24 — Bulk Import / Export Güvenliği V4.2

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

## 4. Parser registry seam
Yeni format eklemek için parser business posting koduyla karışmaz.

Parser contract en az:
- canonical parser/import type key
- supported MIME/extensions
- parser/version
- source column/schema detection
- normalized row DTO contract
- resource limits
- fixture samples
- parser error taxonomy

taşır.

Örnek parser family'leri:
- CSV/XLSX
- MT940
- UBL/XML
- provider settlement exports
- carrier exports
- legacy migration files
- future OCR-reviewed structured output

Parser yalnız normalized DTO üretir; AccountTransaction/StockMovement/TreasuryMovement yazamaz.

Universal “her dosyayı otomatik yorumlayan” parser yoktur. Her business import type kendi allow-listed mapping/validation'ını korur.

## 5. Güvenlik
- permission + company scope server-side fixed
- MIME/extension/content sniff
- file/row/decompression limits
- formula/CSV injection defense
- randomized non-executable temp/storage
- checksum
- parser time/memory limits
- risk bazlı malware scan
- temp lifecycle cleanup

## 6. Preview / dry-run
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

## 7. Validasyon
Satır bazında:
- required fields
- format/precision
- reference/master match
- unique/duplicate
- authorization/company
- business invariant
- quantity/amount caps

Hatalar export edilebilir result file/manifest ile verilir.

## 8. Chunking / transaction
Küçük güvenli import tek transaction olabilir. Büyük import:
- chunked
- restartable
- her row/entity veya güvenli küçük group uygun transaction
- partial commit semantics explicit
- processed/error status manifest
kullanır.

Tek hatalı satır diğer doğruları gereksiz rollback ettirmez; ancak aynı business aggregate'ın atomicity sınırı bölünmez.

## 9. Restart / idempotency
- import batch identity
- stable source/row/client-operation identity where required
- processed/error status
- retry-safe processing
- source-effect uniqueness

Aynı file hash tek başına her business senaryoda yeterli dedupe değildir.

## 10. Duplicate handling
Deterministic identity önceliklidir. Fuzzy match yalnız suggestion'dır; cari/ürün/finans auto-merge yoktur.

## 11. Cari import
Cari master/opening/account transaction import company scope + signed balance reconciliation kullanır. OpenItem/settlement target authority değildir.

## 12. Ürün import
Target tek satış + tek alış fiyatı taşır. Import çoklu fiyat kolonları içerirse canonical sale/purchase mapping açıkça seçilir.

Lot/seri input varsa V1 target'a sessiz authority olarak eklenmez; migration/import policy ile raporlanır.

Future ProductFamily/Variant grouping input'u gerçek family feature aktif değilse Product authority'yi değiştirmez; explicit mapping/review ister.

## 13. Stok import
Opening/adjustment normal StockMovement use-case'i üzerinden gider. Quantity, warehouse/location, cost, source identity validate edilir.

## 14. Banka ekstresi import
Akış:
`Dosya Seç → Önizleme → Eşleştirme → İçe Aktar`.

Formatlar:
- Excel
- CSV
- MT940

Statement row stable identity/fingerprint internal duplicate guard olarak kullanılabilir; kullanıcıya teknik fingerprint gösterilmez.

## 15. Marketplace bulk
External entity identity provider-account scoped tutulur. External order identity ile provider message/event identity ayrıdır. Duplicate event ikinci Mars entity/effect üretmez.

## 16. Future provider settlement/import files
Marketplace/payment/shipping/accounting-export gibi gelecekteki provider dosyaları parser registry üzerinden normalized evidence üretir.

Provider settlement file parse edildi diye finance posting otomatik güvenli sayılmaz; external identity + reconcile + Finance use-case gerekir.

## 17. Bulk fiyat güncelleme
Generic çoklu price-list wizard yoktur. Tek satış/alış fiyatı toplu değişecekse:
`Filter/Select → Formula/Value → Preview Old/New → Rounding → Confirm → Product/Pricing Business Action`.

Raw table update yoktur.

## 18. Export
Small export synchronous olabilir. Büyük export:
`Export Request → Job → chunk/stream → storage → ready notification → expiring authorized link`.

Formatlar ihtiyaca göre CSV/XLSX/JSON/PDF.

## 19. Export governance
- permission/company scope
- sensitive field masking/removal
- filter snapshot
- user/request metadata
- artifact expiry/retention
- download audit where needed

Scheduled export runtime authorization'ı tekrar kontrol eder.

## 20. Rapor export
Rapor Merkezi exportu ready report definition + current filters üzerinden çalışır. User-defined raw SQL export yoktur.

## 21. API bulk
Gerçek ihtiyaç varsa batch resource + item-level result kullanılır. HTTP request boyunca tek dev transaction bekletilmez.

## 22. Audit
Kim, ne zaman, hangi import/export type, parser/version, file hash/reference, row counts, success/error counts ve output artifact bilgisi audit edilir. Raw sensitive payload gereksiz yere audit/log'a yazılmaz.
