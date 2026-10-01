# Bağlantı yönetimi

## Bağlantılar

- `master`: MarsProject_Master
- `period`: aktif şirket+yıl DB
- `period_source`: yalnız şirketler arası kopyalama sırasında geçici

Kalıcı ayrı `company` bağlantısı **yoktur**; kartlar period DB'dedir.

## PeriodContext

Web isteğinde:
1. aktif company + period seçimi Master'dan alınır,
2. company_user erişimi kontrol edilir,
3. period_user_access erişimi kontrol edilir,
4. periods.database_name bulunur,
5. `database.connections.period.database` atanır,
6. `DB::purge('period')` + reconnect yapılır.

PeriodContext olmadan PeriodModel sorgusu fail-fast hata vermelidir.

## Queue

Job `company_id + period_id` (ve gerekirse year) taşır. `handle()` başında system context ile period bağlanır. Kullanıcı aksiyonundan doğmuşsa actor_user_id/name ayrıca taşınabilir.

## FK

Aynı period DB içindeki kart/belge ilişkileri gerçek FK'dir. Master↔period cross-DB FK yoktur.

## Çok dönem

Her period DB sırayla sorgulanır; sonuç PHP'de birleştirilir. `document_date` filtreleri kullanılır.
