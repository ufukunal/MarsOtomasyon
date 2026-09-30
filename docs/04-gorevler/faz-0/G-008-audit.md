# G-008 — İşlem geçmişi

## Amaç
Kim neyi ne zaman değiştirdi. Bu sistem tek kayıt tuttuğu için vazgeçilmez.

## Önkoşul
G-003

## Adımlar

1. activitylog migration'ını çalıştır
2. `activity_log` tablosuna `company_id` ekle:

```php
Schema::table('activity_log', function (Blueprint $table) {
    $table->foreignId('company_id')->nullable()->after('id')->constrained();
    $table->index(['company_id','created_at']);
});
```

3. Global observer: her activity kaydına aktif şirketi yaz

```php
Activity::saving(function (Activity $activity) {
    $activity->company_id ??= CompanyContext::id();
});
```

4. `LogsActivity` trait'ini şu modellere ekle: `Company`, `CompanyLink`,
   `PostingPeriod`, `PrintProfile`, `User`

5. Giriş/çıkış/başarısız giriş olaylarını logla
   (`Illuminate\Auth\Events\Login|Logout|Failed` dinleyicileri)

## Loglanmayacaklar
Liste görüntüleme, arama, rapor açma. Gürültü yaratır.

## Kabul ölçütü
- Şirket adı değiştirilince `activity_log`'a eski ve yeni değer düşer
- Kayıtta `company_id` dolu
- Başarısız giriş denemesi loglanır

## İstem
> activity_log tablosuna company_id kolonu ekleyen migration yaz.
> Activity saving olayında company_id'yi CompanyContext'ten dolduran bir
> service provider kaydı ekle. Company, CompanyLink, PostingPeriod,
> PrintProfile ve User modellerine LogsActivity trait'ini ekle ve
> getActivitylogOptions metodunu yaz. Login, Logout, Failed olayları için
> dinleyici ekle.
