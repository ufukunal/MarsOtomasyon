# G-018 — Para aritmetiği, eşzamanlılık ve tarih kuralları

## Amaç
Üç temel altyapı: BCMath tabanlı para hesabı, çift gönderim koruması ve
iyimser kilit, iş tarihi kuralları.

**Bunlar sonradan eklenemez.** Para hesabı float ile yazılırsa her
hesap fonksiyonu yeniden yazılır; `version` kolonu sonradan eklenirse
her form ve her update elden geçirilir.

## Önkoşul
G-001, G-003

## Dokunulacak dosyalar
- `app/Support/Money/Money.php`, `MoneyCast.php`
- `app/Support/Concurrency/IdempotencyKey.php`
- `app/Support/Concurrency/HasOptimisticLock.php` (trait)
- `app/Exceptions/StaleRecordException.php`
- `app/Actions/Documents/ValidateDocumentDate.php`
- `database/migrations/period/xxxx_create_idempotency_keys_table.php`
- `config/app.php` (timezone)


## Şema / Kod

Bu görev yeni tablo gerektirmiyorsa migration ekleme. Mevcut kod örnekleri ve aşağıdaki mimari sözleşme normatiftir.

## 1. Money

`docs/02-is-kurallari/17-para-aritmetigi.md` içindeki `Money` sınıfını
birebir uygula. Kurallar:

- Tutar **hiçbir yerde float olmaz**; string + BCMath
- Para birimi nesnenin içinde; farklı birimler toplanırsa istisna
- Yuvarlama **yalnızca belge toplamında**, 2 hane, yarım yukarı
- KDV oran gruplarına göre toplanıp **bir kez** hesaplanır
- Yuvarlama farkı `rounding_difference` alanında saklanır

`MoneyCast` model cast'i yazılır. **`float` cast kullanılmaz.**

## 2. İstek anahtarı

`idempotency_keys` tablosu ve `IdempotencyKey::run($key, $action, $callback)`
yardımcısı. Davranış:

- Anahtar yoksa: `processing` yazılır, iş çalışır, `done` + sonuç
- `processing` ise: "işlem sürüyor" hatası
- `done` ise: iş **tekrarlanmaz**, önceki sonuç döner
- 7 günden eski kayıtlar gecelik temizlenir

Zorunlu olduğu işlemler: belge kesinleştirme/iptal, tahsilat, ödeme,
transfer, sayım kesinleştirme, dönem devri, içe aktarma.

## 3. İyimser kilit

`version` kolonu ve `HasOptimisticLock` trait'i. Güncelleme
`where('version', $expected)` ile yapılır; etkilenen satır 0 ise
`StaleRecordException`.

Hata mesajı kullanıcıya **hangi alanların değiştiğini** gösterir.

Uygulanacak tablolar: `documents`, `contacts`, `products`,
`price_lists`, `recipes`, ayar tabloları.

## 4. Tarih kuralları

- `APP_TIMEZONE=Europe/Istanbul`
- `timestamp` → UTC saklanır; `date` → olduğu gibi
- **Dönem kilidi ve raporlar `document_date`'e bakar**, `created_at`'e değil
- `ValidateDocumentDate`: dönem aralığı dışı **engellenir**, gelecek
  tarih **uyarı**, kapalı ay **engellenir**
- Vade `document_date` + `term_days`
- Gece yarısını geçen işlemlerde iş tarihi kullanıcıdan alınır

## 5. Deadlock

`DB::transaction(..., attempts: 3)`. Kilit sırası her zaman aynı:
ürün id'leri **sıralanarak** kilitlenir.


## Kurallar

**Faz bağlamı:** Faz 0 — altyapı.

### Göreve özel kararlar
- Money + BCMath; float yok.
- Idempotency ve optimistic lock farklı sorunları çözer.
- Kritik sayaç/bakiye satırlarında lockForUpdate kullan.

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
- `Money::of('0.1')->plus(Money::of('0.2'))` → `0.3000` (float olsa 0.30000000000000004)
- TRY + USD toplanınca istisna
- 100 satırlık, birim fiyat 33,33 olan faturada kuruş farkı **yok**
- Aynı istek anahtarıyla iki kez gönderim → tek belge
- İki kullanıcı aynı belgeyi kaydedince ikincisi `StaleRecordException`
- 2025 tarihli belge 2026 dönemine yazılamıyor
- Kapalı aya `document_date` ile kayıt engelleniyor


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
- [ ] 116. Cache edilen veri işlem anı doğruluğunu bozmamalı.
- [ ] 117. Queue context serialized company+period bilgisini korumalı.
- [ ] 118. Test fixture başka period DB'yi kirletmemeli.
- [ ] 119. Arşiv period salt-okunur davranışı bozulmamalı.
- [ ] 120. Yeni türetilmiş veri integrity kapsamına alınmalı.
- [ ] 121. Runtime connection adını assert et.
- [ ] 122. Aktif period değişiminde eski DB verisi görünmemeli.
- [ ] 123. Erişim iptalinde yeni period isteği reddedilmeli.
- [ ] 124. Migration rollback ve tekrar migrate edilmeli.
- [ ] 125. Unexpected error correlation id doğrulanmalı.
- [ ] 126. Activity log actor snapshot doğrulanmalı.

## İstem
> Money sınıfını ve MoneyCast'i 17-para-aritmetigi.md'deki kodla birebir
> yaz. idempotency_keys tablosunu, IdempotencyKey yardımcısını,
> HasOptimisticLock trait'ini, StaleRecordException'ı ve
> ValidateDocumentDate action'ını yaz. Hiçbir yerde float cast KULLANMA.
> Yuvarlamayı yalnızca belge toplamında yap.
