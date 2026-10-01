# Şirket izolasyonu

**Önce oku:** `docs/00-genel/07-veritabani-mimarisi.md`

## İzolasyon fizikseldir

Kartlar dahil **her şey** şirket+dönem veritabanındadır
(`ABCHolding_2026`). Başka şirketin verisine erişim fiziksel olarak
mümkün değildir; bağlantı o veritabanına açılmaz.

**Global scope yoktur. `company_id` kolonu yoktur.** Yanlış yazılmış bir
sorgu bile başka şirketin verisini göremez — bu, scope tabanlı
izolasyondan çok daha güçlüdür.

Master'da yalnız şirket listesi, dönem listesi ve kullanıcı/yetki vardır;
orada da kart yoktur.

## Aktif şirket ve dönem

- `session('active_company_id')`, `session('active_year')`
- `PeriodContext::use($companyId, $year)` bağlantıyı ayarlar
- Kullanıcı yalnız `company_user`'da bağlı olduğu şirketleri seçebilir
- Kuyruk işleri `company_id` + `year` taşır, `handle()` başında
  `PeriodContext::use()` çağırır

## Tek risk: yanlış bağlantı

Model `period` yerine `master` bağlantısını kullanırsa tablo bulunamaz
hatası alınır. Bu **sessiz sızıntı değil**, görünür hatadır — iyi haber.

Her dönem modeli `PeriodModel`'den, her master modeli `MasterModel`'den
türer. Test: dönem modeline dönem seçilmeden erişim
`NoActivePeriodException` fırlatmalı.

## Şirketler arası kart kopyalama

İzin master'daki `company_copy_permissions` tablosunda. Kopyalama,
kaynak şirketin **aynı yıldaki** dönem veritabanından okur, hedefin
dönem veritabanına yazar.

```php
abort_unless(CompanyCopyPermission::allows($sourceId, CompanyContext::id(), $type), 403);

$source = DB::connection('period_source');     // geçici ikinci bağlantı
```

Kaynak için ikinci bir bağlantı (`period_source`) açılır, okuma biter,
bağlantı kapatılır. Kopyalanan kayıtta `source_company_id` ve
`source_record_id` saklanır; canlı bağ kurulmaz.
