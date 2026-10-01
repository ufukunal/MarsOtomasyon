# G-104 — Cari kartı

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, migration
`database/migrations/period/` altına yazılır. `company_id` kolonu YOKTUR.


## Amaç
Sistemin en çok kullanılan kartı. Müşteri ve tedarikçi **tek karttır**,
kategoriyle ayrılır, bakiye tektir.

## Önkoşul
G-0b2 (tablo bileşeni), G-0b3 (form bileşenleri)

## Dokunulacak dosyalar
- `database/migrations/xxxx_create_contacts_table.php`
- `app/Models/Contact.php`
- `app/Livewire/Pages/Contacts/ContactList.php` + blade
- `app/Livewire/Pages/Contacts/ContactForm.php` + blade
- `app/Policies/ContactPolicy.php`

## Şema / Kod
```php
Schema::connection('period')->create('contacts', function (Blueprint $table) {
    $table->id();

    $table->string('code', 30);
    $table->string('title');
    $table->string('type', 10)->default('legal');        // legal | real
    $table->string('tax_office')->nullable();
    $table->string('tax_number', 20)->nullable();
    $table->string('national_id', 11)->nullable();
    $table->text('address')->nullable();
    $table->string('city', 60)->nullable();
    $table->string('district', 60)->nullable();
    $table->string('phone', 30)->nullable();
    $table->string('email')->nullable();
    $table->unsignedSmallInteger('term_days')->nullable();
    $table->decimal('risk_limit', 18, 4)->default(0);
    $table->decimal('discount_rate', 7, 4)->default(0);
    $table->foreignId('price_list_id')->nullable()->constrained('price_lists');
    $table->unsignedBigInteger('source_company_id')->nullable(); // cross-DB provenance; FK YOK
    $table->unsignedBigInteger('source_record_id')->nullable();
    $table->boolean('is_active')->default(true);
    $table->timestamps();
    $table->softDeletes();
    $table->unique('code');
    $table->index('title');
    $table->index('tax_number');
});
```

## Model
- `use LogsActivity, SoftDeletes, HasAttachments;`
- `getTermDaysAttribute()` → boşsa `company->default_term_days`
- İlişkiler: `categories()`, `addresses()`, `people()`, `banks()`
- `balance` şimdilik 0 döner (Faz 3'te `contact_transactions` gelecek)

## Liste ekranı

Kolonlar: Kod · Unvan (altında yetkili) · Kategori (rozet) · İl ·
Bakiye (sağa hizalı, renkli) · Risk durumu (rozet) · eylemler

Filtreler: kategori, il, aktif/pasif, limit aşanlar
Arama: kod, unvan, vergi no, telefon, e-posta
Eylemler: Yeni Cari · Dışa Aktar · satır: Düzenle, Cari Ekstre (Faz 3)

## Form ekranı

Bölüm **Firma / Ticari**: kod (salt okunur, otomatik), unvan, tip,
vergi dairesi/no, TC, kategori (çoklu), adres, il, ilçe, telefon, e-posta
Bölüm **Ticari koşullar**: vade günü (ipucu: boşsa şirket varsayılanı),
iskonto %, risk limiti (ipucu: aşımda uyarı verilir, engellenmez), durum

## Kurallar
- Kod otomatik: `CR` + 7 hane, kayıt sonrası değiştirilemez
- Vergi no girildiyse şirket içinde benzersiz olmalı (uyarı, engel değil)
- Silme yerine pasife alma önerilir; hareketi olan cari silinemez


## Kurallar

### Göreve özel kararlar
- Cari müşteri/tedarikçi tek karttır.
- Bakiye contacts üzerinde tutulmaz; `contact_transactions` Faz 3'te tek kaynak olur.
- Risk limiti blok değil uyarıdır.
- `source_company_id` Master companies'a FK değildir.

### Kanonik Faz 1 mimari sözleşmesi
- Bu görevdeki bütün kart/iş tabloları PERIOD veritabanındadır; `company_id` kolonu eklenmez.
- Period modelleri `PeriodModel`'den türer; `BelongsToCompany` ve şirket global scope'u kullanılmaz.
- Şirket izolasyonu `PeriodContext` ile fiziksel veritabanı seçimidir.
- Master yalnız şirket, dönem, kullanıcı, rol/izin, dönem erişimi, kur, ayar, kopyalama izni, print profile ve master audit tutar.
- Period içindeki kart ilişkileri gerçek PostgreSQL foreign key kullanır.
- Period kaydından Master `users` veya `companies` tablosuna gerçek FK kurulmaz.
- Cross-company provenance gerekiyorsa `source_company_id` scalar bigint + `source_record_id` scalar bigint tutulur.
- Aynı şirket yıl devrinde taşınan kartların ID ve kodları korunur.
- Pasif kart kodu başka karta tekrar verilmez.
- Şirketler arası kopyalamada hedefte yeni ID üretilir.
- Yeni kart tablosu dönem devri kapsamına eklenmelidir.
- Tüm kart tablolarında uygun `is_active` davranışı fiziksel silmeye tercih edilir.
- Searchable ana alanlar normalize `search_index` kullanır; PostgreSQL `pg_trgm` indeksi düşünülür.
- Money alanları `decimal(18,4)`, miktar `decimal(18,3)`, oran `decimal(7,4)`, dönüşüm `decimal(18,6)`.
- PHP float para/miktar hesabında kullanılmaz; Money/BCMath kurallarına uyulur.
- İş kuralı Livewire bileşenine gömülmez; Action/Domain sınıfına yazılır.
- Durum değiştiren istek idempotency key ile korunur.
- Düzenlenebilir kayıtlarda `version` optimistic lock kullanılır.
- İhlal edilemez kurallar migration CHECK constraint ile de korunur.
- Beklenmeyen hata correlation id taşır; DomainException iş kuralı hatasıdır.
- Dosya yüklemesi varsa MIME içerikten doğrulanır, UUID fiziksel ad kullanılır, SVG yasaktır.
- `cost.view` yoksa maliyet değeri HTML/JSON/export/payload içinde hiç üretilmez.
- Stok ve cari bakiyesi cache edilmez.
- Period cache key şirket+yıl bağlamı taşır.
- Türetilmiş veya kopyalanmış her değer için ilgili `integrity:` kontrolü eklenir.
- Testler gerçek PostgreSQL üzerinde çalışır; SQLite kullanılmaz.
- Yeni UI ekranı boş/kendi default verisiyle açılır; başka kaydın örnek verisi sızmaz.
- Liste ekranı ilk satırı varsayılan seçili açmaz.
- Filament ve Tailwind eklenmez; Livewire 3 + tek düz CSS kullanılır.
- Yeni business kararı gerekiyorsa model uydurmaz; kullanıcıya karar konusu raporlanır.

### Kabul/test matrisi
- [ ] Doğru kayıt oluşturulur ve DB'den tekrar okunur.
- [ ] Zorunlu alan eksikliği doğrulama hatası üretir.
- [ ] Duplicate code DB unique constraint ile reddedilir.
- [ ] Aynı kod farklı şirketin ayrı period DB'sinde kullanılabilir.
- [ ] Aynı period'da pasif kart olsa dahi kod tekrar kullanılamaz.
- [ ] PeriodContext olmadan erişim fail-fast olur.
- [ ] Yanlış company+period erişimi Master katmanında reddedilir.
- [ ] İki period arasında veri sızıntısı olmaz.
- [ ] Cross-DB FK üretilmediği schema inspection ile doğrulanır.
- [ ] Period içi FK öksüz kayıt oluşmasını engeller.
- [ ] Actor snapshot gerekiyorsa user id/name doğru yazılır.
- [ ] Optimistic lock aynı kaydı iki kullanıcı düzenlerken kaybı engeller.
- [ ] Idempotency aynı state change'i iki kez oluşturmaz.
- [ ] Transaction hata halinde tüm yan etkileri geri alır.
- [ ] DomainException stack trace yerine Türkçe iş hatası verir.
- [ ] Unexpected exception correlation id döndürür.
- [ ] Search normalize Türkçe karakterlerle doğru sonuç verir.
- [ ] pg_trgm indeksinin gerçek PostgreSQL'de varlığı doğrulanır.
- [ ] Yetkisiz kullanıcı butonu görmez ve doğrudan istekte 403 alır.
- [ ] cost.view olmayan kullanıcı maliyet verisini payload/exportta görmez.
- [ ] Yeni form örnek veri olmadan açılır.
- [ ] Liste ilk satırı seçili açılmaz.
- [ ] Activity log kritik değişikliği kaydeder.
- [ ] Archive/devir etkisi görev kapsamındaysa test edilir.
- [ ] Yeni kart yıl devri sonrası aynı ID/kodla oluşur.
- [ ] Şirketler arası kopya senaryosunda hedef yeni ID üretir.
- [ ] Source provenance alanları scalar olarak kalır.
- [ ] Pint sonucu temiz.
- [ ] Larastan sonucu temiz.
- [ ] Pest sonucu temiz.

### Claude kod inceleme matrisi
- [ ] Migration doğru `Schema::connection('period')` kullanıyor.
- [ ] `company_id` kolonu yok.
- [ ] `BelongsToCompany` trait yok.
- [ ] Şirket global scope yok.
- [ ] Aynı period içindeki ilişkiler gerçek FK.
- [ ] Cross-DB FK yok.
- [ ] `source_company_id` varsa `constrained('companies')` kullanılmıyor.
- [ ] Kart kodu period DB içinde benzersiz.
- [ ] Pasif kart kodu tekrar kullanılmıyor.
- [ ] Model `PeriodModel` kullanıyor.
- [ ] Arama alanı normalize ediliyor.
- [ ] Trigram indeks gerçek PostgreSQL migration'ında oluşturuluyor.
- [ ] UI permission kontrolü rota+bileşen+Action/Policy seviyesinde.
- [ ] Action kritik validation'ı tekrar yapıyor.
- [ ] `version` optimistic lock gözden geçirilmiş.
- [ ] Idempotency state-changing action'da uygulanmış.
- [ ] Audit actor/correlation bilgisi taşıyor.
- [ ] Period actor Master user'a FK oluşturmuyor.
- [ ] Yeni tablo yıl devri kopyalama listesine dahil.
- [ ] ID/kod aynı şirket devirde korunuyor.
- [ ] Şirketler arası kopyada yeni ID varsayımı korunuyor.
- [ ] Yeni türetilmiş alan integrity kapsamına alınmış.
- [ ] Decimal precision kararlarla aynı.
- [ ] Para hesabında float yok.
- [ ] cost.view sızıntısı yok.
- [ ] Dosya güvenlik kuralları uygulanmış.
- [ ] Liste query'si aktif `period` bağlantısından çıkmıyor.
- [ ] Queue işi session'a güvenmiyor.
- [ ] Testte iki farklı period DB kullanılmış.
- [ ] Yanlış period verisi görünmüyor.
- [ ] Kapalı period/archived davranışı gerekiyorsa test edilmiş.
- [ ] Migration rollback edilebilir.
- [ ] Pint geçiyor.
- [ ] Larastan level 6 geçiyor.
- [ ] Pest gerçek PostgreSQL'de geçiyor.
- [ ] Yeni CSS dosyası/Tailwind/Filament yok.
- [ ] Form başka karttan veriyle dolu açılmıyor.
- [ ] İlk liste satırı otomatik seçilmiyor.
- [ ] Export izinleri UI ile aynı.
- [ ] Yeni business karar uydurulmamış.

## Kabul ölçütü
- Cari açılıyor, kod otomatik geliyor
- Aynı kod ikinci kez eklenemiyor
- Farklı şirkette aynı kod eklenebiliyor (izolasyon)
- `contacts.create` izni olmayan kullanıcı Yeni Cari düğmesini görmüyor
  ve doğrudan istek gönderse 403 alıyor


### Ek Claude doğrulama senaryoları
- [ ] 01. Modelin `$connection` değerini runtime testinde doğrula.
- [ ] 02. Migration'da company_id olmadığını schema introspection ile doğrula.
- [ ] 03. Aynı kodun aynı period'da ikinci kez reddedildiğini doğrula.
- [ ] 04. Aynı kodun başka şirket period DB'sinde mümkün olduğunu doğrula.
- [ ] 05. Pasif kart kodunun yeniden kullanılamadığını doğrula.
- [ ] 06. Devir sözleşmesinde ID+code korunacağını doğrula.
- [ ] 07. Cross-company copy durumunda yeni ID üretildiğini doğrula.
- [ ] 08. Provenance alanının FK olmadığını doğrula.
- [ ] 09. Search index'in Türkçe normalizasyonunu doğrula.
- [ ] 10. Yetkisiz kullanıcının Action seviyesinde de reddedildiğini doğrula.
- [ ] 11. Activity log'un old/new değerleri uygun olayda taşıdığını doğrula.
- [ ] 12. Formun başka kayıttan örnek veri almadığını doğrula.
- [ ] 13. Liste ilk satırın otomatik seçilmediğini doğrula.
- [ ] 14. Export permission parity testini doğrula.
- [ ] 15. Queue/import işi varsa period context'i açıkça kurduğunu doğrula.
- [ ] 16. Toplu import kısmi sessiz başarı bırakmadığını doğrula.
- [ ] 17. Integrity kontrolü otomatik fix yapmadığını doğrula.
- [ ] 18. Rollback sonrası şemanın tekrar migrate edilebildiğini doğrula.
- [ ] 19. Pest gerçek PostgreSQL database name'lerini ayırdığını doğrula.
- [ ] 20. Yeni business karar eklenmediğini doğrula.
- [ ] 21. Modelin `$connection` değerini runtime testinde doğrula.
- [ ] 22. Migration'da company_id olmadığını schema introspection ile doğrula.
- [ ] 23. Aynı kodun aynı period'da ikinci kez reddedildiğini doğrula.
- [ ] 24. Aynı kodun başka şirket period DB'sinde mümkün olduğunu doğrula.
- [ ] 25. Pasif kart kodunun yeniden kullanılamadığını doğrula.
- [ ] 26. Devir sözleşmesinde ID+code korunacağını doğrula.
- [ ] 27. Cross-company copy durumunda yeni ID üretildiğini doğrula.
- [ ] 28. Provenance alanının FK olmadığını doğrula.
- [ ] 29. Search index'in Türkçe normalizasyonunu doğrula.
- [ ] 30. Yetkisiz kullanıcının Action seviyesinde de reddedildiğini doğrula.
- [ ] 31. Activity log'un old/new değerleri uygun olayda taşıdığını doğrula.
- [ ] 32. Formun başka kayıttan örnek veri almadığını doğrula.
- [ ] 33. Liste ilk satırın otomatik seçilmediğini doğrula.
- [ ] 34. Export permission parity testini doğrula.
- [ ] 35. Queue/import işi varsa period context'i açıkça kurduğunu doğrula.
- [ ] 36. Toplu import kısmi sessiz başarı bırakmadığını doğrula.
- [ ] 37. Integrity kontrolü otomatik fix yapmadığını doğrula.
- [ ] 38. Rollback sonrası şemanın tekrar migrate edilebildiğini doğrula.
- [ ] 39. Pest gerçek PostgreSQL database name'lerini ayırdığını doğrula.
- [ ] 40. Yeni business karar eklenmediğini doğrula.
- [ ] 41. Modelin `$connection` değerini runtime testinde doğrula.
- [ ] 42. Migration'da company_id olmadığını schema introspection ile doğrula.
- [ ] 43. Aynı kodun aynı period'da ikinci kez reddedildiğini doğrula.
- [ ] 44. Aynı kodun başka şirket period DB'sinde mümkün olduğunu doğrula.
- [ ] 45. Pasif kart kodunun yeniden kullanılamadığını doğrula.
- [ ] 46. Devir sözleşmesinde ID+code korunacağını doğrula.
- [ ] 47. Cross-company copy durumunda yeni ID üretildiğini doğrula.
- [ ] 48. Provenance alanının FK olmadığını doğrula.
- [ ] 49. Search index'in Türkçe normalizasyonunu doğrula.
- [ ] 50. Yetkisiz kullanıcının Action seviyesinde de reddedildiğini doğrula.
- [ ] 51. Activity log'un old/new değerleri uygun olayda taşıdığını doğrula.
- [ ] 52. Formun başka kayıttan örnek veri almadığını doğrula.
- [ ] 53. Liste ilk satırın otomatik seçilmediğini doğrula.
- [ ] 54. Export permission parity testini doğrula.
- [ ] 55. Queue/import işi varsa period context'i açıkça kurduğunu doğrula.
- [ ] 56. Toplu import kısmi sessiz başarı bırakmadığını doğrula.
- [ ] 57. Integrity kontrolü otomatik fix yapmadığını doğrula.
- [ ] 58. Rollback sonrası şemanın tekrar migrate edilebildiğini doğrula.
- [ ] 59. Pest gerçek PostgreSQL database name'lerini ayırdığını doğrula.
- [ ] 60. Yeni business karar eklenmediğini doğrula.
- [ ] 61. Modelin `$connection` değerini runtime testinde doğrula.
- [ ] 62. Migration'da company_id olmadığını schema introspection ile doğrula.
- [ ] 63. Aynı kodun aynı period'da ikinci kez reddedildiğini doğrula.
- [ ] 64. Aynı kodun başka şirket period DB'sinde mümkün olduğunu doğrula.
- [ ] 65. Pasif kart kodunun yeniden kullanılamadığını doğrula.
- [ ] 66. Devir sözleşmesinde ID+code korunacağını doğrula.
- [ ] 67. Cross-company copy durumunda yeni ID üretildiğini doğrula.
- [ ] 68. Provenance alanının FK olmadığını doğrula.
- [ ] 69. Search index'in Türkçe normalizasyonunu doğrula.
- [ ] 70. Yetkisiz kullanıcının Action seviyesinde de reddedildiğini doğrula.
- [ ] 71. Activity log'un old/new değerleri uygun olayda taşıdığını doğrula.
- [ ] 72. Formun başka kayıttan örnek veri almadığını doğrula.
- [ ] 73. Liste ilk satırın otomatik seçilmediğini doğrula.
- [ ] 74. Export permission parity testini doğrula.
- [ ] 75. Queue/import işi varsa period context'i açıkça kurduğunu doğrula.
- [ ] 76. Toplu import kısmi sessiz başarı bırakmadığını doğrula.
- [ ] 77. Integrity kontrolü otomatik fix yapmadığını doğrula.
- [ ] 78. Rollback sonrası şemanın tekrar migrate edilebildiğini doğrula.
- [ ] 79. Pest gerçek PostgreSQL database name'lerini ayırdığını doğrula.
- [ ] 80. Yeni business karar eklenmediğini doğrula.
- [ ] 81. Modelin `$connection` değerini runtime testinde doğrula.
- [ ] 82. Migration'da company_id olmadığını schema introspection ile doğrula.
- [ ] 83. Aynı kodun aynı period'da ikinci kez reddedildiğini doğrula.
- [ ] 84. Aynı kodun başka şirket period DB'sinde mümkün olduğunu doğrula.
- [ ] 85. Pasif kart kodunun yeniden kullanılamadığını doğrula.
- [ ] 86. Devir sözleşmesinde ID+code korunacağını doğrula.
- [ ] 87. Cross-company copy durumunda yeni ID üretildiğini doğrula.
- [ ] 88. Provenance alanının FK olmadığını doğrula.
- [ ] 89. Search index'in Türkçe normalizasyonunu doğrula.
- [ ] 90. Yetkisiz kullanıcının Action seviyesinde de reddedildiğini doğrula.
- [ ] 91. Activity log'un old/new değerleri uygun olayda taşıdığını doğrula.
- [ ] 92. Formun başka kayıttan örnek veri almadığını doğrula.
- [ ] 93. Liste ilk satırın otomatik seçilmediğini doğrula.
- [ ] 94. Export permission parity testini doğrula.
- [ ] 95. Queue/import işi varsa period context'i açıkça kurduğunu doğrula.
- [ ] 96. Toplu import kısmi sessiz başarı bırakmadığını doğrula.
- [ ] 97. Integrity kontrolü otomatik fix yapmadığını doğrula.
- [ ] 98. Rollback sonrası şemanın tekrar migrate edilebildiğini doğrula.
- [ ] 99. Pest gerçek PostgreSQL database name'lerini ayırdığını doğrula.
- [ ] 100. Yeni business karar eklenmediğini doğrula.
- [ ] 101. Modelin `$connection` değerini runtime testinde doğrula.
- [ ] 102. Migration'da company_id olmadığını schema introspection ile doğrula.
- [ ] 103. Aynı kodun aynı period'da ikinci kez reddedildiğini doğrula.

## İstem
> contacts tablosu için migration, Contact modeli, ContactList ve ContactForm
> Livewire bileşenlerini ve ContactPolicy'yi yaz. Şemayı birebir uygula.
> Model LogsActivity, SoftDeletes ve HasAttachments
> trait'lerini kullansın. Liste ekranı DataTableComponent'ten türesin.
> Kod otomatik üretilsin (CR + 7 hane) ve kayıt sonrası salt okunur olsun.
