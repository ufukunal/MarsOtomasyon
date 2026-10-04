# G-008 — İşlem geçmişi

## Amaç
Kim neyi ne zaman değiştirdi. Bu sistem tek kayıt tuttuğu için vazgeçilmez.

## Önkoşul
G-003


## Dokunulacak dosyalar
- Master activity_log migration/config genişletmesi
- Period activity_log migration/config genişletmesi
- `app/Support/Audit/AuditContext.php`
- login/logout/failed-login audit listener'ları
- period actor/correlation audit helper'ı
- `tests/Feature/Audit/MasterAuditTest.php`
- `tests/Feature/Audit/PeriodAuditTest.php`


## Şema / Kod

Bu görevde aşağıdaki mevcut kod/şema örnekleri normatiftir. Yeni tablo gerekmiyorsa migration ekleme; mevcut mimari sözleşmeyi bozacak ek şema uydurma.

## Adımlar

1. Master ve period için activity log migration/connection ayrımını kur.
2. Master activity log: login/failed login, kullanıcı/rol/izin, şirket/dönem, period erişimi, print profile ve company_copy_permissions değişikliklerini tut.
3. Period activity log: kart, belge, stok/cari kritik işlem, fiyat sapması, dönem aç/kapa ve şirketler arası kopyalama hedef işlemlerini tut.
4. Period activity log'a `company_id` ekleme. Bunun yerine actor snapshot ve correlation bilgisi ekle:

```php
Schema::connection('period')->table('activity_log', function (Blueprint $table) {
    $table->unsignedBigInteger('actor_user_id')->nullable();
    $table->string('actor_user_name')->nullable();
    $table->uuid('correlation_id')->nullable()->index();
    $table->index('created_at');
});
```

5. Master user ile period DB arasında FK kurma.
6. Login/Logout/Failed olayları yalnız Master audit'e yazılır.
7. Period işleminde actor_user_id/name authenticated Master kullanıcısından snapshot olarak alınır.
8. `CompanyLink` gibi legacy model kullanma; geçerli model `CompanyCopyPermission`dır.

## Loglanmayacaklar
Liste görüntüleme, arama, rapor açma. Gürültü yaratır.


## Kurallar

**Faz bağlamı:** Faz 0 — altyapı; sonraki fazların sözleşmesini bozmamalı.

### Bu göreve özel kanonik notlar
- Master ve period activity_log ayrıdır.
- Period audit'e company_id ekleme; actor_user_id + actor_user_name snapshot kullan.


### Uygulama ayrıntıları
- Master audit ile period audit ayrı tutulur.
- Master audit login, kullanıcı/rol/izin, şirket/dönem ve Master ayar değişikliklerini izler.
- Period audit kart, belge, stok/cari kritik işlem ve dönem aç/kapa olaylarını izler; period audit'e `company_id` eklenmez.
- Period actor bilgisi `actor_user_id + actor_user_name + correlation_id` ile snapshot olarak tutulur.

## Kabul ölçütü
- Şirket adı değiştirilince `activity_log`'a eski ve yeni değer düşer
- Period audit kaydında actor_user_id/name ve correlation_id dolu; company_id yok
- Başarısız giriş denemesi loglanır


## İstem
> activity_log tablosuna company_id kolonu ekleyen migration yaz.
> Master ve period activity loglarını ayrı bağlantılarda uygula. Period log'a company_id ekleme; actor_user_id + actor_user_name snapshot ve correlation_id kullan. Login/Logout/Failed yalnız Master audit'e yazılsın.