# G-005 — Kullanıcı, rol, izin

**Veritabanı: MASTER.** Kullanıcı ve yetki şirket/dönem üstüdür.

## Amaç
Yedi rol, ekran bazlı izinler ve `cost.view` özel izni.

## Önkoşul
G-003

## Dokunulacak dosyalar
- `database/migrations/xxxx_add_fields_to_users_table.php`
- `database/migrations/xxxx_create_company_user_table.php`
- `database/seeders/RoleSeeder.php`
- `app/Models/User.php`


## Şema / Kod

Bu görevde aşağıdaki mevcut kod/şema örnekleri normatiftir. Yeni tablo gerekmiyorsa migration ekleme; mevcut mimari sözleşmeyi bozacak ek şema uydurma.

## users ek alanlar

```php
$table->boolean('is_active')->default(true);
$table->foreignId('last_company_id')->nullable()->constrained('companies');
$table->foreignId('last_period_id')->nullable()->constrained('periods');
```

## company_user

```php
Schema::connection('master')->create('company_user', function (Blueprint $table) {
    $table->id();
    $table->foreignId('company_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->timestamps();
    $table->unique(['company_id','user_id']);
});
```

## period_user_access

```php
Schema::connection('master')->create('period_user_access', function (Blueprint $table) {
    $table->id();
    $table->foreignId('period_id')->constrained('periods')->cascadeOnDelete();
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->boolean('is_active')->default(true);
    $table->jsonb('permission_overrides')->nullable();
    $table->timestamps();
    $table->unique(['period_id','user_id']);
});
```

Şirket erişimi tek başına period erişimi vermez. `company_user` + `period_user_access` birlikte kontrol edilir. Dönem devri tamamlandıktan sonra seçilen kullanıcıların erişimi ve `permission_overrides` değerleri yeni döneme kopyalanabilir.

## Roller

`Yönetici`, `Muhasebe`, `Satış`, `Satınalma`, `Depo`, `Üretim`, `Görüntüleyici`

## İzinler

Her ekran için dört izin: `<ekran>.view|create|update|cancel`.
Faz 0'da yalnızca şu ekranlar için üret:
`companies`, `users`, `roles`, `periods`, `audit`, `print_profiles`, `company_copy_permissions`

**Ayrıca bağımsız izin:** `cost.view`

## Rol → izin eşlemesi (Faz 0)

| Rol | İzinler |
|---|---|
| Yönetici | hepsi + `cost.view` |
| Muhasebe | periods.*, audit.view, print_profiles.* + `cost.view` |
| Satış | print_profiles.view (**`cost.view` YOK**) |
| Satınalma | print_profiles.view + `cost.view` |
| Depo | print_profiles.view |
| Üretim | print_profiles.view + `cost.view` |
| Görüntüleyici | *.view (**`cost.view` YOK**) |

## Seed kullanıcı
`admin@mars.local` / parola `password` / rol Yönetici / iki şirkete de bağlı


## Kurallar

**Faz bağlamı:** Faz 0 — altyapı; sonraki fazların sözleşmesini bozmamalı.

### Bu göreve özel kanonik notlar
- Master'da period_user_access oluştur ve şirket+dönem erişimini ayrı tut.
- Dönem devrinde erişim/permission_overrides seçilerek yeni döneme kopyalanabilecek yapıda ol.


### Uygulama ayrıntıları
- `company_user` şirket erişimini, `period_user_access` dönem erişimini ayrı tutar.
- Şirket erişimi tek başına period erişimi vermez; ikisi birlikte doğrulanır.
- `permission_overrides` dönemsel istisnalar içindir; temel rol/permission tanımları Master'da ortak kalır.
- Dönem devri bu tablonun seçili kullanıcı kayıtlarını yeni döneme kopyalayabilecek şekilde tasarlanır.

## Kabul ölçütü
```bash
php artisan migrate:fresh --seed
```
- Satış rolündeki kullanıcı `cost.view` iznine sahip **olmamalı**
- Yönetici tüm izinlere sahip olmalı


## İstem
> users tablosuna is_active, last_company_id ve last_period_id ekle. period_user_access tablosunu oluştur. company_user pivot
> tablosunu oluştur. RoleSeeder yaz: yukarıdaki yedi rolü, listelenen
> ekranlar için dörder izni ve cost.view iznini üretsin, tablodaki eşlemeye
> göre rollere atasın. admin@mars.local kullanıcısını Yönetici olarak
> iki şirkete bağla. spatie permission teams=true olduğu için izinleri
> şirket bazında ata.
