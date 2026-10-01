# Bağlantı yönetimi

## Bağlantılar

- `master`: MarsProject_Master
- `period`: aktif şirket+yıl DB
- `period_source`: yalnız şirketler arası kopyalama sırasında geçici

Kalıcı ayrı `company` bağlantısı **yoktur**; kartlar period DB'dedir.

## PeriodContext

Web isteğinde:
1. aktif `company_id + period_id` seçimi Master'dan alınır,
2. `periods.id = period_id` kaydının aynı `company_id`'ye ait olduğu doğrulanır,
3. `company_user` erişimi kontrol edilir,
4. `period_user_access` erişimi kontrol edilir,
5. ancak bundan sonra `periods.database_name` aktif `period` bağlantısına atanır,
6. `DB::purge('period')` + reconnect yapılır.

`PeriodContext::use(companyId, periodId)` bağlantı kurar; kullanıcıya ait erişim doğrulaması user-facing middleware/Action tarafından **çağrıdan önce** yapılır. System/queue işleri yalnız daha önce doğrulanmış şirket+dönem bağlamıyla çağırır.

PeriodContext olmadan PeriodModel sorgusu fail-fast hata vermelidir.

## Queue

Job `company_id + period_id` (ve gerekirse year) taşır. `handle()` başında system context ile period bağlanır. Kullanıcı aksiyonundan doğmuşsa actor_user_id/name ayrıca taşınabilir.

## FK

Aynı period DB içindeki kart/belge ilişkileri gerçek FK'dir. Master↔period cross-DB FK yoktur.

## Çok dönem

Her period DB sırayla sorgulanır; sonuç PHP'de birleştirilir. `document_date` filtreleri kullanılır.
