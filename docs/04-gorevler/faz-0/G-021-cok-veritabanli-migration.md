# G-021 — migrate:periods (çok veritabanlı dağıtım)

## Amaç
Dönem tablosuna eklenen bir kolon **tüm dönem veritabanlarında**
çalışmalıdır. 2 şirket × 3 yıl = 6 veritabanı; beş yılda 10+ olur.

Birini atlarsan o dönem açıldığında "column does not exist" hatası
alınır ve bu ancak kullanıcı o döneme geçince fark edilir.

**Bu, master/dönem mimarisinin doğurduğu en büyük işletim sorunudur.**

## Önkoşul
G-002, G-003


## Dokunulacak dosyalar
- Bu görevde tarif edilen mevcut uygulama/migration/test dosyaları; kapsam dışına çıkma.


## Şema / Kod

Bu görev yeni tablo gerektirmiyorsa migration ekleme. Mevcut kod örnekleri ve aşağıdaki mimari sözleşme normatiftir.

## Komut

```bash
php artisan migrate:periods              # tüm aktif + kapalı
php artisan migrate:periods --year=2026
php artisan migrate:periods --company=1
php artisan migrate:periods --status     # hangi dönem hangi migration'da
php artisan migrate:periods --pretend
```

Kod: `docs/02-is-kurallari/20-migration-ve-dagitim.md` içindeki
`MigratePeriodsCommand` sınıfını birebir uygula.

## Kurallar

- Arşiv dönemler **atlanır**; geri yüklendiğinde ayrıca çalıştırılır
- Bir dönem hata verirse komut devam eder ama **sonuçta hata döner**;
  dağıtım betiği durur
- Sonunda rapor: kaç dönem güncellendi, hangileri hatalı
- `periods.schema_version` güncellenir
- Ana sayfada uyum kontrolü: "3 dönem güncellenmemiş" uyarısı

## Migration yazma disiplini

- Dönem migration'ı **geri alınabilir** olmalı (`down()` yazılır)
- **Veri taşıyan migration yazılmaz** — kolon eklenir, veri ayrı
  komutla doldurulur. Uzun süren işlem altı veritabanında dağıtımı kilitler
- Yeni dönem veritabanı hep güncel şemayla oluşur (`CreatePeriod`)

## Dağıtım betiği

```
1. down --secret=...
2. git pull
3. composer install --no-dev -o
4. migrate --database=master --path=database/migrations/master --force
5. migrate:periods --force          ← HATA VERİRSE DUR
6. optimize:clear && optimize
7. permission:cache-reset
8. queue:restart
9. up
```

**Dağıtım öncesi tüm veritabanlarının yedeği alınır** (master dahil).
Geri alma `down()` metoduna değil, yedeğe dayanır.


## Kurallar

**Faz bağlamı:** Faz 0 — altyapı.

### Göreve özel kararlar
- Master migration ve migrate:periods ayrıdır.
- Aktif/kapalı/restored period DB'ler schema version uyumuyla güncellenir.
- Restore period önce migrate edilir sonra closed açılır.

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
- 4 dönem veritabanı varken komut dördünü de güncelliyor
- `--status` hangi dönemin hangi migration'da olduğunu gösteriyor
- Bir dönem hata verince komut hata koduyla çıkıyor
- Arşiv dönem atlanıyor
- Yeni oluşturulan dönem zaten güncel şemada


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
- [ ] 127. Hassas alan export/payload içinde olmamalı.
- [ ] 128. Constraint gerçek PostgreSQL'de ihlali reddetmeli.
- [ ] 129. Integrity farkı raporlamalı.
- [ ] 130. Kod Tailwind/Filament bağımlılığı eklememeli.
- [ ] 131. Yeni dosya görev kapsamı dışında olmamalı.
- [ ] 132. Pint/Larastan/Pest sonuçları temiz olmalı.
- [ ] 133. İş kuralı Livewire'a gömülmemeli.
- [ ] 134. document_date gereken yerde created_at kullanılmamalı.
- [ ] 135. Money gereken yerde float kullanılmamalı.
- [ ] 136. Cache edilen veri işlem anı doğruluğunu bozmamalı.
- [ ] 137. Queue context serialized company+period bilgisini korumalı.
- [ ] 138. Test fixture başka period DB'yi kirletmemeli.

## İstem
> MigratePeriodsCommand'ı yaz. Tüm aktif ve kapalı dönemleri dolaşsın,
> arşivleri atlasın, her biri için PeriodContext::use çağırıp migrate
> çalıştırsın. Hatalı olanları toplayıp sonunda raporlasın ve hata
> koduyla çıksın. --year, --company, --status, --pretend seçeneklerini
> destekle. Dağıtım betiğini docs/isletim/ altına yaz.
