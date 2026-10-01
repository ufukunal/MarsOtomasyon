# Dönem kilidi

Kontrol tarihi `document_date`dır. `created_at` kilit kararı için kullanılmaz.

`EnsurePeriodOpen(document_date)` period DB'deki `posting_periods` kaydını okur. `company_id` filtresi yoktur.

Kapalı ayda posted belge, stok hareketi, cari hareketi, kasa/banka hareketi üretilemez. Taslak düzenleme politikası görevde açıkça belirtilir; kesinleştirme mutlaka engellenir.

Yeniden açma **özel izin + gerekçe** ister; yalnız rol adına bağlanmaz. Actor user_id + user_name snapshot ve gerekçe period activity_log'a yazılır.
