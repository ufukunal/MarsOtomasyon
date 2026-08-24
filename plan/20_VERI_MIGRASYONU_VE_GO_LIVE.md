# 20 — Veri Migrasyonu ve Go-Live

## İlke
`MarsEski` kodu taşınmaz; gerekli master/transaction geçmişi kontrollü ETL ile yeni PostgreSQL modeline aktarılır.

## Migrasyon aşamaları
1. Kaynak envanteri
2. Alan eşleme sözlüğü
3. Temizleme/normalizasyon
4. Dry-run import
5. Hata/uyumsuzluk raporu
6. Tekrar edilebilir import
7. Reconciliation
8. Cutover

## Master sıra
Öneri:
`firma/şube → kullanıcı gerekirse → cariler → ürün/barkod → depo/lokasyon → kasa/banka → açık çek/senet → gerekli başlangıç bakiyeleri/stoklar`.

## Tarihsel veri
Her eski hareketi yeni sisteme birebir taşımak zorunlu değildir. Operasyonel/yasal ihtiyaç belirlenir:
- tam tarihçe
- belirli dönem
- açılış bakiyesi + arşiv erişimi

Karar veri seti bazında verilir.

## Açılış stok/cari
Açılışlar özel source type ile ledger'a girer; keyfi balance set edilmez. Toplamlar eski sistem mutabakat raporlarıyla doğrulanır.

## Dry-run
Import aynı kaynakla tekrar çalıştırılabilir olmalı ve duplicate üretmemelidir. Satır bazlı error file ve toplam reconciliation çıkarılır.

## Go-live checklist
- production config/secrets
- PostgreSQL backup
- restore doğrulaması
- migration dry-run green
- son delta/cutover planı
- queue/scheduler/Valkey health
- admin kullanıcı/2FA
- kritik smoke test
- satış faturası/tahsilat/mal kabul/ödeme örnek işlemleri
- rapor toplam mutabakatı

## Cutover
Kaynak sistem write freeze veya kontrollü delta penceresi uygulanır. Son veri alındıktan sonra reconciliation yapılmadan yeni sistem authoritative ilan edilmez.

## Rollback
Go-live rollback, yeni sistemde üretilecek business kayıtlarının eski sisteme nasıl geri döneceği gerçeğini dikkate alır. Bu nedenle kısa ve kontrollü cutover penceresi + backup + validation tercih edilir.

## Arşiv
Eski repo/veri kaynakları read-only arşiv olarak saklanabilir; yeni production uygulama runtime bağımlılığı olmaz.
