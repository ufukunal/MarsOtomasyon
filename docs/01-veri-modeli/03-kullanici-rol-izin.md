# Kullanıcı, rol, izin ve dönem erişimi

**Veritabanı: MASTER**

## users

Laravel kullanıcı alanlarına `is_active`, `last_company_id` ve `last_period_id` eklenebilir. Bu alanlar yalnız kullanım kolaylığıdır; erişim yetkisi değildir.

## company_user

Kullanıcının hangi şirketleri görebildiğini tutar. company_id + user_id unique.

## period_user_access

Şirket yetkisinden ayrı olarak dönem erişimi tutulur.

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

PeriodContext kurulmadan company_user + period_user_access doğrulanır.

## Devir

Yeni dönem devri tamamlandıktan sonra önceki period_user_access kayıtlarını kullanıcı seçerek kopyalama diyaloğu açılır. `permission_overrides` varsa seçilen kullanıcı için kopyalanır. Roller/permissions Master'da ortak olduğu için çoğaltılmaz.

## Period kayıtlarında actor

Period DB içindeki created_by/posted_by vb. Master user ID'sini scalar tutar; gerçek FK kurulmaz. Yanında `created_by_name` / `posted_by_name` gibi snapshot alanı bulunur.

## Bağımsız izinler

`cost.view`, `reports.consolidated`, `sales.quote.approve`, dönem yeniden açma izni.
