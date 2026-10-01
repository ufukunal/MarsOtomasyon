# G-115 — Satınalma talebi ve teklif toplama (basit)

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer.

## Amaç
Satınalma döngüsü doğrudan siparişle başlıyordu. Önüne iki basit halka
ekleniyor: **talep** ve **tedarikçi teklifi karşılaştırma**.

Basit tutulur: onay zinciri, bütçe kontrolü, RFQ e-postası **yok**.

## Önkoşul
G-104 (cari), G-106 (ürün)


## Dokunulacak dosyalar
- Görevde tarif edilen migration/model/action/Livewire/test dosyaları; kapsam dışına çıkma.


## Şema / Kod

Bu bölümdeki mevcut şema örnekleri aşağıdaki kanonik mimari kurallarla birlikte uygulanır. Çelişkide kanonik kurallar üstündür.

## Şemalar

```php
// purchase_requests
$table->id();
$table->string('number', 40)->nullable();          // kesinleşince verilir
$table->date('request_date');
$table->unsignedBigInteger('requested_by')->nullable(); // Master user scalar
$table->string('requested_by_name')->nullable();
$table->string('department', 60)->nullable();
$table->date('needed_by')->nullable();
$table->string('status', 15)->default('draft');     // draft|open|quoted|ordered|cancelled
$table->text('note')->nullable();
$table->timestamps();

// purchase_request_lines
$table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
$table->foreignId('product_id')->constrained();
$table->string('product_code', 40);
$table->decimal('quantity', 18, 3);
$table->foreignId('unit_id')->constrained('units');
$table->text('note')->nullable();

// supplier_quotes  — tedarikçiden gelen teklif
$table->foreignId('purchase_request_id')->constrained();
$table->foreignId('contact_id')->constrained();     // tedarikçi
$table->date('quote_date');
$table->date('valid_until')->nullable();
$table->char('currency', 3)->default('TRY');
$table->decimal('exchange_rate', 18, 6)->default(1);
$table->unsignedSmallInteger('delivery_days')->nullable();
$table->boolean('is_selected')->default(false);
$table->text('note')->nullable();

// supplier_quote_lines
$table->foreignId('supplier_quote_id')->constrained()->cascadeOnDelete();
$table->foreignId('purchase_request_line_id')->constrained();
$table->decimal('unit_price', 18, 4);               // KDV hariç
$table->decimal('quantity', 18, 3);
```

## Akış

```
1. Talep açılır: ürün + miktar + ihtiyaç tarihi        → draft
2. "Teklif İste" → status = open, numara verilir
3. Tedarikçi teklifleri ELLE girilir (e-posta/telefonla gelen)
4. Karşılaştırma ekranı: satır bazında en ucuz vurgulanır
5. Bir teklif seçilir (is_selected)                    → quoted
6. "Siparişe Dönüştür" → satınalma siparişi oluşur     → ordered
```

## Karşılaştırma ekranı

Satırlar ürün, kolonlar tedarikçi. Her hücrede birim fiyat ve toplam.
En ucuz hücre yeşil. Altta tedarikçi bazında genel toplam, teslim süresi
ve para birimi. Farklı para birimindeki teklifler **belge tarihinin
kuruyla** TRY'ye çevrilerek karşılaştırılır.

## Kurallar
- Talep kesinleşmeden teklif girilemez
- Bir talepten **tek sipariş** çıkar; kısmi sipariş isteniyorsa talep bölünür
- Seçilmeyen teklifler saklanır (geçmiş fiyat bilgisi)
- Siparişe dönüşünce talep `ordered`, değiştirilemez
- Fiyat girişinde maliyet sapma uyarısı **çalışmaz** (henüz alış değil)


## Kurallar

### Göreve özel kararlar
- Basit satınalma talebi + teklif toplama; onay zinciri/bütçe/RFQ e-postası kapsam dışı.
- Period DB'dedir ve document_date/actor snapshot kurallarına uyacak şekilde hazırlanır.

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
- Talep açılıyor, numara kesinleşmede veriliyor
- Üç tedarikçi teklifi girilip karşılaştırılıyor, en ucuz vurgulanıyor
- Farklı para birimi TRY'ye çevrilerek kıyaslanıyor
- Seçilen tekliften sipariş oluşuyor, satırlar ve fiyatlar taşınıyor
- Talep `ordered` olunca değiştirilemiyor


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

## İstem
> purchase_requests, purchase_request_lines, supplier_quotes ve
> supplier_quote_lines tabloları için migration, modeller, talep ekranı,
> teklif giriş ekranı, karşılaştırma ekranı ve siparişe dönüştürme
> action'ını yaz. Onay zinciri, bütçe kontrolü veya e-posta gönderimi
> EKLEME. Para birimi farkını belge tarihinin kuruyla çöz.
