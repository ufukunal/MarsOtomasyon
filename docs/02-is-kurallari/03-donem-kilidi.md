# Dönem kilidi

Kontrol tarihi `document_date`dır. `created_at` kilit kararı için kullanılmaz.

`EnsurePeriodOpen(document_date)` **önce `document_date.year === PeriodContext::year()` kontrolü yapar**. Yıl uyuşmazsa `PeriodYearMismatchException` ile işlem reddedilir; yanlış yıl için posting_periods lookup yapılmaz.

Yıl eşleşiyorsa period DB'deki `posting_periods` kaydı okunur. `company_id` filtresi yoktur.

Kapalı ayda posted belge, stok hareketi, cari hareketi, kasa/banka hareketi üretilemez. Taslak düzenleme politikası görevde açıkça belirtilir; kesinleştirme mutlaka engellenir.

Yeniden açma **özel izin + gerekçe** ister; yalnız rol adına bağlanmaz. Actor user_id + user_name snapshot ve gerekçe period activity_log'a yazılır.


## Yıl sınırı

Fiziksel `ABCHolding_2026` DB'sine 2025/2027 iş tarihli belge/hareket yazılamaz. Satırı olmayan ayın açık kabul edilmesi yalnız **aktif period yılı içindeki** aylar için geçerlidir.
