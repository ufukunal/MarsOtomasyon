# 25 — Migration ve Schema Değişiklik Playbook V4

## 1. Amaç
Production PostgreSQL 18 schema değişikliklerini veri kaybı, uzun lock ve gereksiz downtime oluşturmadan yapmak.

## 2. Default: Expand / Contract
### Expand
Yeni nullable/default-safe column/table/index eklenir. Eski code çalışmaya devam eder.

### Compatible code
Yeni application kısa geçiş döneminde old/new schema ile uyumlu çalışır. Gerekirse dual-write/read fallback explicit ve geçicidir.

### Backfill
- chunked
- restartable
- idempotent
- metrics/progress
- long transaction yok
- concurrent-write safe

### Verify
Counts/checksums yanında domain reconciliation yapılır.

### Switch
Yeni field/path authority olur.

### Contract — sonraki release
Old read/write kaldırılır; kullanım kalmadığı doğrulanır; destructive cleanup ayrı safer deploy'da yapılır.

## 3. DDL risk sınıfları
- Low: küçük yeni tablo / nullable column
- Medium: large-table index/column/backfill
- High: type rewrite, large NOT NULL, drop/rename, büyük table rewrite

Medium/high değişiklik staging rehearsal + PostgreSQL lock/rewrite behavior review ister.

## 4. PostgreSQL DDL
Laravel migration syntax çalışıyor diye operasyonel risk bitmiş sayılmaz. Değerlendir:
- lock level
- table rewrite
- index build duration
- migration transaction behavior
- backup/replication impact if applicable

`CREATE INDEX CONCURRENTLY`, constraint validation vb. PostgreSQL özellikleri ihtiyaç olduğunda kontrollü runbook ile kullanılabilir.

## 5. Additive change
Yeni column/table/index backward-compatible eklenir. Application breaking schema ile aynı anda zorunlu deploy edilmez.

## 6. Rename / remove
Direct rename compatibility bozuyorsa:
`add new → compatible read/write → backfill → verify → switch → later remove old`.

## 7. NOT NULL / constraint
Büyük existing table:
1. nullable/additive field
2. new writer doğru değeri yazmaya başlar
3. backfill
4. validation
5. PostgreSQL-safe NOT NULL/constraint

## 8. Concurrent-write-safe backfill
Backfill canlı writer'ın yeni verisini stale snapshot ile ezemez.

Güvenli patternler:
1. **new writer first:** compatible app yeni field/representation'ı doğru yazmaya başlar, sonra historical rows backfill edilir
2. **conditional update:** `WHERE new_field IS NULL` ve/veya expected version/checksum şartı
3. gerekli yerde temporary dual-write + deterministic reconciliation
4. backfill sonrası missed/concurrent rows için restartable catch-up

Money/stock/ledger dönüşümünde yalnız row-count eşitliği yeterli değildir; domain totals/reconciliation gerekir.

## 9. Index
Index gerçek access pattern/query plan gerekçesine dayanır. Large table'da build lock/duration staging'de ölçülür. Failed/partial index creation recovery planı bulunur.

## 10. Enum / state
DB enum değişikliğinin deployment kısıtları dikkate alınır. Application Enum/value contract ile DB constraint migration-safe tutulur. State rename/removal mevcut row'ları anlamsız bırakmaz.

## 11. Ledger değişiklikleri
`account_transactions`, `stock_movements`, treasury/instrument source-effect schema değişiklikleri özel review ister.

Geçmiş kaydın ekonomik anlamını değiştiren backfill:
- explicit migration reason
- idempotent command/job
- before/after reconciliation
- audit/result artifact
ister.

Silent delete/rewrite yoktur.

## 12. Data migration ≠ schema migration
Milyonlarca business row conversion migration PHP dosyasına tek transaction olarak gömülmez. Restartable command/job kullanılır. Legacy migration `20_VERI_MIGRASYONU_VE_GO_LIVE.md` ile yönetilir.

## 13. Deployment sırası
1. backup/restore readiness
2. additive migration
3. compatible application deploy
4. backfill/projection rebuild
5. validation/reconciliation/metrics
6. new path switch
7. old path kullanımını durdur
8. sonraki release'te contract cleanup

## 14. Roll-forward preference
Irreversible production migration sonrası rollback yalnız eski binary'ye dönmek değildir. Forward-fix planı zorunludur.

## 15. CI / rehearsal
Her schema değişiklikte ilgili ölçekte:
- fresh PostgreSQL 18 migration
- existing schema upgrade path
- touched application tests
- migration smoke

Riskli değişiklikte ayrıca:
- representative data volume rehearsal
- lock/duration measurement
- backup/restore readiness
- forward-fix scenario

## 16. Main-only çalışma güvenliği
Main branch kullanılması riskli migration'ı kör push etme gerekçesi değildir. Migration atomic commit, review ve ilgili Gate A/B/C ile ilerler.

## 17. Yasaklar
- undocumented manual production ALTER
- finalized ledger data silent delete/rewrite
- ölçmeden blocking large-table migration
- schema + application breaking değişikliğini tek zorunlu adımda yapmak
- backfill sırasında concurrent writer'ı yok saymak

## 18. Checklist
- [ ] PostgreSQL 18 migration/test geçti mi?
- [ ] table size/cardinality biliniyor mu?
- [ ] lock/rewrite riski biliniyor mu?
- [ ] old/new app compatibility var mı?
- [ ] concurrent writer/backfill race policy var mı?
- [ ] backfill restartable/idempotent mi?
- [ ] business reconciliation var mı?
- [ ] destructive step sonraki release'e ertelendi mi?
- [ ] backup/restore path hazır mı?
- [ ] monitoring/error recovery var mı?
- [ ] forward-fix planı var mı?
