# 25 — Migration ve Schema Değişiklik Playbook

## Amaç
Production PostgreSQL schema değişikliklerini veri kaybı ve uzun downtime oluşturmadan yapmak.

## Temel kural
Destructive migration mümkünse tek release'te yapılmaz. Expand → migrate/backfill → switch → contract yaklaşımı kullanılır.

## Additive değişiklik
Yeni kolon/tablo/index önce backward-compatible eklenir. Yeni kod eski ve yeni schema geçiş penceresinde çalışabilmelidir.

## NOT NULL
Büyük tabloda doğrudan default + NOT NULL lock riskine göre değerlendirilir:
1. nullable kolon
2. backfill
3. validation
4. NOT NULL constraint

## Rename/Remove
Kolon rename gerekiyorsa önce yeni kolon/uyumluluk katmanı eklenir, data migrate edilir, uygulama yeni alana geçirilir, eski alan sonraki release'te kaldırılır.

## Index
Büyük production tablolarında PostgreSQL'in concurrent index seçenekleri ve migration transaction davranışı dikkate alınır. Query plan ölçülür.

## Enum/state
DB enum değişikliğinin deployment kısıtları göz önünde tutulur. State contract application + DB constraint arasında migration-safe yönetilir.

## Backfill
- chunk'lı
- restart edilebilir
- idempotent
- progress ölçülebilir
- business saatinde DB'yi kilitlemeyecek şekilde

## Ledger değişiklikleri
`account_transactions` ve `stock_movements` schema değişiklikleri özel review ister. Geçmiş kayıtların anlamını değiştiren backfill açık reconciliation testine tabidir.

## Deployment sırası
1. backup/restore readiness
2. additive migration
3. compatible application deploy
4. backfill/projection rebuild
5. validation/metrics
6. old path kullanımını kapatma
7. sonraki release'te contract cleanup

## Rollback
Migration irreversible ise rollback yalnız eski binary'ye dönmek değildir; veri/schema forward-fix planı gerekir. Her migration PR'ında rollback/forward-fix notu bulunur.

## CI
Fresh migration + existing schema upgrade path test edilir. PostgreSQL 18 üzerinde migration smoke test zorunludur.

## Yasaklar
- production'da elle undocumented ALTER
- business ledger verisini sessiz delete
- büyük tabloda ölçmeden blocking migration
- schema ile application'ın aynı anda zorunlu breaking değişmesi
