# Export ve çok dönemli raporlama

## Formatlar

- ekran
- PDF
- XLSX
- CSV

PDF Browsershot ile üretilir. XLSX/CSV aynı rapor query sonucundan gelir.

Numeric hücreler numeric yazılır; para hesabı float ile yapılmaz.

## Büyük işler

Ağır export queue olabilir:
queued → processing → done|failed.

Queue payload:
- company id
- period ids
- report key
- validated filters
- actor
- permission scope snapshot/reference

Session PeriodContext'e güvenilmez.

## Çok dönem

K-084/K-206:

- her period DB ayrı sorgulanır,
- normal cross-DB JOIN yok,
- PHP DTO/Collection katmanında birleştirilir,
- her satır period_id/year taşır,
- context try/finally ile geri yüklenir,
- archived/detached period sessiz atlanmaz.

Aynı şirket dönemlerinde korunmuş kart ID/kodları kullanılabilir. Cross-company konsolidasyonda yalnız açık business key/report tanımıyla birleştirme yapılır.

## Preset

Kullanıcı filtre/kolon/sort preset kaydedebilir.
Shared preset company kapsamındadır.
Preset sorgu formülü değiştiremez.
