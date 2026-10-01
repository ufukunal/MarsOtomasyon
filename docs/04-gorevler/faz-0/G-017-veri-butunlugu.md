# G-017 — Veri bütünlüğü altyapısı

## Amaç
Yazmanın gerçekten olduğunu ve tutarlı olduğunu kontrol eden dört katman.
Tek kayıt tutan bir sistemde sessiz bozulma kabul edilemez.

## Önkoşul
G-003, G-006

## Dokunulacak dosyalar
- `app/Support/Integrity/IntegrityCheck.php` (arayüz)
- `app/Support/Integrity/Checks/StockBalanceCheck.php`
- `app/Support/Integrity/Checks/DocumentTotalCheck.php`
- `app/Support/Integrity/Checks/ContactBalanceCheck.php`
- `app/Support/Integrity/Checks/NumberSeriesCheck.php`
- `app/Console/Commands/IntegrityCommand.php`
- `database/migrations/period/xxxx_create_integrity_reports_table.php`
- `app/Livewire/Pages/Settings/IntegrityReport.php`


## Şema / Kod

Bu görev yeni tablo gerektirmiyorsa migration ekleme. Mevcut kod örnekleri ve aşağıdaki mimari sözleşme normatiftir.

## integrity_reports

```php
Schema::connection('period')->create('integrity_reports', function (Blueprint $table) {
    $table->id();
    $table->string('check_name', 40);
    $table->timestamp('run_at');
    $table->unsignedInteger('checked_count')->default(0);
    $table->unsignedInteger('mismatch_count')->default(0);
    $table->json('details')->nullable();
    $table->unsignedInteger('duration_ms')->default(0);
    $table->timestamps();
    $table->index(['check_name','run_at']);
});
```

## Arayüz

```php
interface IntegrityCheck
{
    public function name(): string;
    public function run(): IntegrityResult;   // checked, mismatches[], duration
}
```

## Komutlar

```bash
php artisan integrity:all            # tüm kontroller, tüm aktif dönemler
php artisan integrity:stock
php artisan integrity:documents
php artisan integrity:contacts
php artisan integrity:numbers
```

`integrity:all` **tüm aktif dönem veritabanlarını** dolaşır:
`PeriodContext::use()` ile her döneme geçer, kontrolü çalıştırır,
sonunda eski bağlamı geri yükler.

## Zamanlama

```php
Schedule::command('integrity:all')->dailyAt('03:00');
```

Fark bulunursa Yönetici'ye bildirim. Fark yoksa sessiz.

## Ekran — Ayarlar › Bütünlük Kontrolü

Liste: kontrol adı, son çalışma, kontrol edilen kayıt, fark sayısı, süre.
Detay: farkların tablosu (kayıt, hesaplanan, saklanan, fark).
Eylem: "Şimdi çalıştır" ve "Yeniden hesapla" (yalnızca Yönetici,
gerekçe zorunlu, `activity_log`'a düşer).

**Otomatik düzeltme yok.** Sebep bilinmeden düzeltmek asıl hatayı gizler.

## Ana sayfa göstergesi

"Son bütünlük kontrolü: 30.09.2026 03:00 · fark yok"
Üç günden eski veya fark varsa **kırmızı uyarı**.

## CHECK kısıtları

Bu görevde ayrıca, mevcut migration'lara CHECK kısıtları eklenir:
`docs/02-is-kurallari/16-veri-butunlugu.md` içindeki SQL bloğunu uygula.


## Kurallar

**Faz bağlamı:** Faz 0 — altyapı.

### Göreve özel kararlar
- Kart referansları period içi FK ile korunur; eski master reference kontrolü kullanılmaz.
- integrity:carry ID/kod/stok-bakiye sürekliliğini doğrular.
- Integrity otomatik düzeltmez.

### Kanonik mimari
- Kaynak önceliği: son kullanıcı kararı > GUNCELLEME-PROMPT > karar günlüğü > DB mimarisi > görev.
- Period tablolarında company_id yoktur; izolasyon fiziksel DB'dir.
- Period modelinde BelongsToCompany/global scope kullanılmaz.
- Master modelinde şirket global scope kullanılmaz.
- Şirket+dönem erişimi Master'da doğrulanmadan PeriodContext kurulmaz.
- Period actor alanlarında Master users'a FK kurulmaz; user_id + user_name snapshot tutulur.
- Aynı period içindeki ilişkiler gerçek FK kullanır.
- Migration dizinleri master/ ve period/ olarak ayrıdır.
- Period dağıtımı migrate:periods üzerinden yapılır.
- Money + BCMath kullanılır; para hesabında PHP float yasaktır.
- document_date iş tarihidir; created_at değildir.
- Durum değiştiren istek idempotency key taşır.
- Düzenlenebilir iş kaydı version optimistic lock kullanır.
- Kritik iş kuralları CHECK constraint ile de korunur.
- DomainException iş kuralı hatasıdır; unexpected hata correlation id taşır.
- Dosya MIME içerikten doğrulanır; UUID fiziksel ad; SVG yasak.
- Stok ve cari bakiyesi cache edilmez.
- Period cache anahtarı şirket+yıl bağlamı taşır; bağlam yoksa exception.
- Türetilmiş/kopyalanmış veri integrity kontrolü alır.
- Testler gerçek PostgreSQL üzerinde çalışır; SQLite kabul edilmez.
- cost.view yoksa maliyet verisi HTML/JSON/export içinde hiç üretilmez.
- İş kuralı Livewire'a gömülmez; Action/Domain katmanındadır.
- Filament/Tailwind eklenmez; tek düz CSS tema kullanılır.
- Posted kayıt yerinde değiştirilmez; ters kayıt kullanılır.
- Yeni iş kararı gerekiyorsa model uydurmaz; kullanıcı kararı ister.

### Kabul/test matrisi
- [ ] Mutlu yol sonucu DB'den tekrar okunarak doğrulanır.
- [ ] Yetkisiz kullanıcı erişimi reddedilir.
- [ ] İki şirket + iki period DB ile veri sızıntısı testi yapılır.
- [ ] PeriodContext yoksa fail-fast test edilir.
- [ ] Master/period connection karışmadığı doğrulanır.
- [ ] Transaction rollback yarım kayıt bırakmaz.
- [ ] Idempotency çift kayıt üretmez.
- [ ] Optimistic lock eski version ile overwrite'ı engeller.
- [ ] Kapalı dönem document_date ile engellenir.
- [ ] Decimal/Money beklenenleri production helper'dan bağımsız hesaplanır.
- [ ] CHECK constraint doğrudan DB ihlalini reddeder.
- [ ] Cross-DB user FK oluşmadığı doğrulanır.
- [ ] Actor snapshot Master erişilemese de geçmişi okunur tutar.
- [ ] Queue varsa session olmadan doğru period'a bağlanır.
- [ ] Arama varsa normalize search_index + trigram gerçek PostgreSQL'de doğrulanır.
- [ ] Upload varsa sahte MIME ve SVG reddedilir.
- [ ] Export varsa cost.view sızıntısı yoktur.
- [ ] Cache varsa çıplak anahtar kullanılmaz.
- [ ] Integrity farkı raporlar, otomatik düzeltmez.
- [ ] Pint geçer.
- [ ] Larastan seviye 6 yeni hata bırakmaz.
- [ ] Pest gerçek PostgreSQL üzerinde geçer.

### Claude inceleme kontrolü
- [ ] Doğru connection açıkça seçilmiş.
- [ ] Period migration company_id içermiyor.
- [ ] BelongsToCompany/global scope yok.
- [ ] Cross-DB FK yok.
- [ ] Period içi FK gerçek constraint.
- [ ] company_user + period_user_access ayrımı korunmuş.
- [ ] document_date semantiği doğru.
- [ ] Money/decimal hassasiyetleri doğru.
- [ ] Transaction sınırı Action içinde.
- [ ] Idempotency gerektiği yerde giriş noktasında.
- [ ] version update eski version koşuluyla korunuyor.
- [ ] lockForUpdate yalnız kritik satırda.
- [ ] CHECK constraint migration'da.
- [ ] Audit actor snapshot + correlation id taşıyor.
- [ ] cost.view hassas veri üretimini engelliyor.
- [ ] Cache doğruluk riski taşımıyor.
- [ ] Integrity kontrolü aynı fazda.
- [ ] Gerçek PostgreSQL test senaryosu mevcut.
- [ ] Queue session bağımsız.
- [ ] UI boş form veri sızdırmıyor.
- [ ] Liste ilk satırı varsayılan seçili açmıyor.
- [ ] UI belge numarası üretmiyor.
- [ ] Yeni CSS/Tailwind/Filament yok.
- [ ] Görev dışı refactor yok.
- [ ] Kabul ölçütü davranışı doğruluyor.
- [ ] Migration rollback/tekrar migrate planı net.

## Kabul ölçütü
- `integrity:all` tüm aktif dönemlerde çalışıyor, bağlam geri yükleniyor
- Elle bozulan bir bakiye (`DB::table` ile) kontrolde yakalanıyor
- Fark yoksa rapor `mismatch_count = 0` yazıyor
- CHECK kısıtı ihlal eden insert veritabanı seviyesinde reddediliyor
- "Yeniden hesapla" bakiyeyi hareket toplamına eşitliyor ve loglanıyor
- Ana sayfa göstergesi eski kontrolde kırmızı oluyor


### Ek Claude doğrulama senaryoları
- [ ] 01. Runtime connection adını assert et.
- [ ] 02. Aktif period değişiminde eski DB verisi görünmemeli.
- [ ] 03. Erişim iptalinde yeni period isteği reddedilmeli.
- [ ] 04. Migration rollback ve tekrar migrate edilmeli.
- [ ] 05. Unexpected error correlation id doğrulanmalı.
- [ ] 06. Activity log actor snapshot doğrulanmalı.
- [ ] 07. Hassas alan export/payload içinde olmamalı.
- [ ] 08. Constraint gerçek PostgreSQL'de ihlali reddetmeli.
- [ ] 09. Integrity farkı raporlamalı.
- [ ] 10. Kod Tailwind/Filament bağımlılığı eklememeli.
- [ ] 11. Yeni dosya görev kapsamı dışında olmamalı.
- [ ] 12. Pint/Larastan/Pest sonuçları temiz olmalı.
- [ ] 13. İş kuralı Livewire'a gömülmemeli.
- [ ] 14. document_date gereken yerde created_at kullanılmamalı.
- [ ] 15. Money gereken yerde float kullanılmamalı.
- [ ] 16. Cache edilen veri işlem anı doğruluğunu bozmamalı.
- [ ] 17. Queue context serialized company+period bilgisini korumalı.
- [ ] 18. Test fixture başka period DB'yi kirletmemeli.
- [ ] 19. Arşiv period salt-okunur davranışı bozulmamalı.
- [ ] 20. Yeni türetilmiş veri integrity kapsamına alınmalı.
- [ ] 21. Runtime connection adını assert et.
- [ ] 22. Aktif period değişiminde eski DB verisi görünmemeli.
- [ ] 23. Erişim iptalinde yeni period isteği reddedilmeli.
- [ ] 24. Migration rollback ve tekrar migrate edilmeli.
- [ ] 25. Unexpected error correlation id doğrulanmalı.
- [ ] 26. Activity log actor snapshot doğrulanmalı.
- [ ] 27. Hassas alan export/payload içinde olmamalı.
- [ ] 28. Constraint gerçek PostgreSQL'de ihlali reddetmeli.
- [ ] 29. Integrity farkı raporlamalı.
- [ ] 30. Kod Tailwind/Filament bağımlılığı eklememeli.
- [ ] 31. Yeni dosya görev kapsamı dışında olmamalı.
- [ ] 32. Pint/Larastan/Pest sonuçları temiz olmalı.
- [ ] 33. İş kuralı Livewire'a gömülmemeli.
- [ ] 34. document_date gereken yerde created_at kullanılmamalı.
- [ ] 35. Money gereken yerde float kullanılmamalı.
- [ ] 36. Cache edilen veri işlem anı doğruluğunu bozmamalı.
- [ ] 37. Queue context serialized company+period bilgisini korumalı.
- [ ] 38. Test fixture başka period DB'yi kirletmemeli.
- [ ] 39. Arşiv period salt-okunur davranışı bozulmamalı.
- [ ] 40. Yeni türetilmiş veri integrity kapsamına alınmalı.
- [ ] 41. Runtime connection adını assert et.
- [ ] 42. Aktif period değişiminde eski DB verisi görünmemeli.
- [ ] 43. Erişim iptalinde yeni period isteği reddedilmeli.
- [ ] 44. Migration rollback ve tekrar migrate edilmeli.
- [ ] 45. Unexpected error correlation id doğrulanmalı.
- [ ] 46. Activity log actor snapshot doğrulanmalı.
- [ ] 47. Hassas alan export/payload içinde olmamalı.
- [ ] 48. Constraint gerçek PostgreSQL'de ihlali reddetmeli.
- [ ] 49. Integrity farkı raporlamalı.
- [ ] 50. Kod Tailwind/Filament bağımlılığı eklememeli.
- [ ] 51. Yeni dosya görev kapsamı dışında olmamalı.
- [ ] 52. Pint/Larastan/Pest sonuçları temiz olmalı.
- [ ] 53. İş kuralı Livewire'a gömülmemeli.
- [ ] 54. document_date gereken yerde created_at kullanılmamalı.
- [ ] 55. Money gereken yerde float kullanılmamalı.
- [ ] 56. Cache edilen veri işlem anı doğruluğunu bozmamalı.
- [ ] 57. Queue context serialized company+period bilgisini korumalı.
- [ ] 58. Test fixture başka period DB'yi kirletmemeli.
- [ ] 59. Arşiv period salt-okunur davranışı bozulmamalı.
- [ ] 60. Yeni türetilmiş veri integrity kapsamına alınmalı.
- [ ] 61. Runtime connection adını assert et.
- [ ] 62. Aktif period değişiminde eski DB verisi görünmemeli.
- [ ] 63. Erişim iptalinde yeni period isteği reddedilmeli.
- [ ] 64. Migration rollback ve tekrar migrate edilmeli.
- [ ] 65. Unexpected error correlation id doğrulanmalı.
- [ ] 66. Activity log actor snapshot doğrulanmalı.
- [ ] 67. Hassas alan export/payload içinde olmamalı.
- [ ] 68. Constraint gerçek PostgreSQL'de ihlali reddetmeli.
- [ ] 69. Integrity farkı raporlamalı.
- [ ] 70. Kod Tailwind/Filament bağımlılığı eklememeli.
- [ ] 71. Yeni dosya görev kapsamı dışında olmamalı.
- [ ] 72. Pint/Larastan/Pest sonuçları temiz olmalı.
- [ ] 73. İş kuralı Livewire'a gömülmemeli.
- [ ] 74. document_date gereken yerde created_at kullanılmamalı.
- [ ] 75. Money gereken yerde float kullanılmamalı.
- [ ] 76. Cache edilen veri işlem anı doğruluğunu bozmamalı.
- [ ] 77. Queue context serialized company+period bilgisini korumalı.
- [ ] 78. Test fixture başka period DB'yi kirletmemeli.
- [ ] 79. Arşiv period salt-okunur davranışı bozulmamalı.
- [ ] 80. Yeni türetilmiş veri integrity kapsamına alınmalı.
- [ ] 81. Runtime connection adını assert et.
- [ ] 82. Aktif period değişiminde eski DB verisi görünmemeli.
- [ ] 83. Erişim iptalinde yeni period isteği reddedilmeli.
- [ ] 84. Migration rollback ve tekrar migrate edilmeli.
- [ ] 85. Unexpected error correlation id doğrulanmalı.
- [ ] 86. Activity log actor snapshot doğrulanmalı.
- [ ] 87. Hassas alan export/payload içinde olmamalı.
- [ ] 88. Constraint gerçek PostgreSQL'de ihlali reddetmeli.
- [ ] 89. Integrity farkı raporlamalı.
- [ ] 90. Kod Tailwind/Filament bağımlılığı eklememeli.
- [ ] 91. Yeni dosya görev kapsamı dışında olmamalı.
- [ ] 92. Pint/Larastan/Pest sonuçları temiz olmalı.
- [ ] 93. İş kuralı Livewire'a gömülmemeli.
- [ ] 94. document_date gereken yerde created_at kullanılmamalı.
- [ ] 95. Money gereken yerde float kullanılmamalı.
- [ ] 96. Cache edilen veri işlem anı doğruluğunu bozmamalı.
- [ ] 97. Queue context serialized company+period bilgisini korumalı.
- [ ] 98. Test fixture başka period DB'yi kirletmemeli.
- [ ] 99. Arşiv period salt-okunur davranışı bozulmamalı.
- [ ] 100. Yeni türetilmiş veri integrity kapsamına alınmalı.
- [ ] 101. Runtime connection adını assert et.
- [ ] 102. Aktif period değişiminde eski DB verisi görünmemeli.
- [ ] 103. Erişim iptalinde yeni period isteği reddedilmeli.
- [ ] 104. Migration rollback ve tekrar migrate edilmeli.
- [ ] 105. Unexpected error correlation id doğrulanmalı.
- [ ] 106. Activity log actor snapshot doğrulanmalı.
- [ ] 107. Hassas alan export/payload içinde olmamalı.
- [ ] 108. Constraint gerçek PostgreSQL'de ihlali reddetmeli.
- [ ] 109. Integrity farkı raporlamalı.
- [ ] 110. Kod Tailwind/Filament bağımlılığı eklememeli.
- [ ] 111. Yeni dosya görev kapsamı dışında olmamalı.
- [ ] 112. Pint/Larastan/Pest sonuçları temiz olmalı.
- [ ] 113. İş kuralı Livewire'a gömülmemeli.
- [ ] 114. document_date gereken yerde created_at kullanılmamalı.
- [ ] 115. Money gereken yerde float kullanılmamalı.

## İstem
> IntegrityCheck arayüzünü, dört kontrol sınıfını, IntegrityCommand'ı,
> integrity_reports tablosunu ve Bütünlük Kontrolü ekranını yaz.
> integrity:all tüm aktif dönemleri dolaşsın ve sonunda eski bağlamı
> geri yüklesin. Otomatik düzeltme YAPMA. CHECK kısıtlarını
> 16-veri-butunlugu.md içindeki SQL'e göre migration'lara ekle.
