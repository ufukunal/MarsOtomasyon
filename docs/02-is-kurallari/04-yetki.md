# Yetki ve dönem erişimi

## Master erişim modeli

Kullanıcı şirket erişimi ve **şirket+dönem erişimi** Master DB'de tutulur. Şirket seçicide yalnız erişebildiği şirketler; dönem seçicide yalnız erişebildiği dönemler görünür. PeriodContext kurulmadan bu erişim doğrulanır.

Rol/izin sistemi spatie/laravel-permission ile Master'dadır. Aynı kullanıcı farklı şirketlerde farklı role sahip olabilir. Dönemsel özel erişim/override kayıtları ayrı tutulabilir.

Dönem devri tamamlandıktan sonra kullanıcıya önceki dönemin kullanıcı erişim ve dönemsel yetkilerini yeni döneme kopyalamak isteyip istemediği sorulur; kullanıcılar seçilebilir. Rol tanımlarının kendisi Master'da ortak olduğundan yeniden yaratılmaz.

## Özel izinler

- `cost.view`: maliyet/kâr; yoksa veri hiç üretilmez.
- `reports.consolidated`: konsolide rapor.
- `sales.quote.approve`: teklif iç onayı.
- kapanmış yılı yeniden açma için ayrı izin + gerekçe.

İzin kontrolü menü, rota, Livewire, Action ve Policy katmanlarında yapılır; kritik olan Action kontrolüdür.
