# G-002 — companies ve periods (master)

## Amaç
Şirket kartı ve dönem kaydı. Bağlantı yönetimi bu iki tabloya dayanır.

## Önkoşul
G-001

## Dokunulacak dosyalar
- `database/migrations/master/xxxx_create_companies_table.php`
- `database/migrations/master/xxxx_create_periods_table.php`
- `app/Models/Company.php`, `app/Models/Period.php`
- `app/Actions/Periods/CreatePeriod.php`
- `database/seeders/CompanySeeder.php`


## Şema / Kod

Bu görevde aşağıdaki mevcut kod/şema örnekleri normatiftir. Yeni tablo gerekmiyorsa migration ekleme; mevcut mimari sözleşmeyi bozacak ek şema uydurma.

## Şemalar
`docs/01-veri-modeli/01-companies.md` ve `01b-periods.md` içindeki
şemaları **birebir** uygula. İkisi de `Schema::connection('master')`.

## CreatePeriod action

```php
final class CreatePeriod
{
    public function handle(Company $company, int $year): Period
    {
        $dbName = "{$company->db_prefix}_{$year}";

        if (Period::on('master')->where('database_name', $dbName)->exists()) {
            throw new \RuntimeException("{$dbName} zaten var.");
        }

        DB::connection('master')->statement("CREATE DATABASE \"{$dbName}\"");

        $period = Period::on('master')->create([
            'company_id'    => $company->id,
            'year'          => $year,
            'database_name' => $dbName,
            'starts_on'     => "{$year}-01-01",
            'ends_on'       => "{$year}-12-31",
            'status'        => 'active',
        ]);

        PeriodContext::use($company->id, $year);
        Artisan::call('migrate', [
            '--database' => 'period',
            '--path'     => 'database/migrations/period',
            '--force'    => true,
        ]);

        return $period;
    }
}
```

**Dikkat:** `CREATE DATABASE` transaction içinde çalışmaz. Migration
başarısız olursa oluşan veritabanı elle silinmelidir; action bunu
`try/catch` ile ele alır ve kullanıcıya bildirir.

## Model kuralları
- `Company` ve `Period` → `protected $connection = 'master'`
  (bunlar `MasterModel`'den türemez, çünkü `company_id` taşımazlar)
- `db_prefix` kayıt sonrası salt okunur; yalnızca harf, rakam, alt çizgi
- `Period::databaseName()` → `{db_prefix}_{year}`

## Seed
| code | name | db_prefix | dönemler |
|---|---|---|---|
| ABCHOLDING | ABC Holding | ABCHolding | 2026 |
| XYZLTD | XYZ Ltd. Şti. | XYZltd | 2026 |


## Kurallar

**Faz bağlamı:** Faz 0 — altyapı; sonraki fazların sözleşmesini bozmamalı.

### Bu göreve özel kanonik notlar
- companies ve periods Master DB'dedir.
- Period satırı company_id, year, database_name, status bilgilerini taşır; business kartı tutmaz.

### Proje çapında zorunlu mimari sözleşme
- Kaynak önceliği: kullanıcı son kararı > GUNCELLEME-PROMPT > karar günlüğü > DB mimarisi > görev.
- Period tablolarında company_id yoktur.
- Period modellerinde BelongsToCompany/global scope yoktur.
- Master modellerinde de şirket global scope'u kullanılmaz.
- Şirket izolasyonu fiziksel period DB seçimidir.
- Kullanıcı period erişimi Master'da şirket + dönem bazında doğrulanır.
- PeriodContext erişim doğrulanmadan kurulmaz.
- Kuyruk işi session'a güvenmez; company_id + period_id/yıl bağlamı taşır.
- Master users ile period kayıtları arasında PostgreSQL FK kurulmaz.
- Period actor alanı user_id + user_name snapshot olarak tutulur.
- Aynı period içindeki kart/işlem ilişkilerinde gerçek FK kullanılır.
- Migration'lar master/ ve period/ dizinlerine ayrılır.
- Dağıtımda period şemaları migrate:periods üzerinden güncellenir.
- PHP float para hesabında kullanılmaz.
- Money + BCMath kullanılır.
- Tutar decimal(18,4), miktar decimal(18,3), oran decimal(7,4), kur/dönüşüm decimal(18,6) kullanılır.
- İhlal edilemez kurallar DB CHECK ile de korunur.
- Durum değiştiren HTTP/Livewire eylemlerinde idempotency düşünülür.
- Düzenlenebilir iş kaydında version optimistic lock kullanılır.
- İş tarihi gereken yerde document_date kullanılır; created_at yerine geçmez.
- DomainException iş kuralı hatasıdır ve beklenmeyen hata gibi loglanmaz.
- Beklenmeyen hata correlation id taşır.
- Dosya MIME tipi içerikten doğrulanır; SVG kabul edilmez.
- Stok ve cari bakiyesi cache edilmez.
- CacheKey period anahtarı şirket+yıl bağlamı olmadan üretilemez.
- Türetilmiş/kopyalanmış veri aynı görevde integrity kontrolü alır.
- Gerçek PostgreSQL kullanılmadan kabul testi tamamlanmış sayılmaz.
- SQLite, lockForUpdate/CHECK/trigram testinin yerine geçmez.
- Yetkisiz veri HTML, JSON, export veya Livewire payload içinde üretilmez.
- cost.view yoksa maliyet değeri yalnız gizlenmez, hiç oluşturulmaz.
- Yeni CSS dosyası açma; tek tema dosyasını kullan.
- Filament ve Tailwind ekleme.
- Action iş kuralını taşır; Livewire yalnız orchestration/form katmanıdır.
- Posted kayıt fiziksel silinmez ve yerinde düzeltilmez; ters kayıt gerekir.
- Taslak numarasız olabilir ve ilgili karar uyarınca fiziksel silinebilir.
- Kod benzersizliği period DB kapsamındadır; pasif kart kodu tekrar kullanılmaz.
- Aynı şirket dönem devrinde taşınan kart ID/kodları korunur.
- Şirketler arası kopyalamada hedef yeni ID üretir.
- Cross-company provenance source_company_id + source_record_id scalar'dır; cross-DB FK değildir.
- Yeni iş kararı gerekiyorsa görev içinde uydurma yapılmaz; kullanıcı kararı istenir.

### Test ve kabul matrisi
- [ ] Mutlu yol veritabanından tekrar okunarak doğrulanmalı.
- [ ] Yetkisiz kullanıcı için 403 veya beklenen DomainException doğrulanmalı.
- [ ] Yanlış period DB'ye veri yazılmadığı iki şirket/two-period senaryosuyla doğrulanmalı.
- [ ] PeriodContext yokken fail-fast davranış test edilmeli.
- [ ] Master bağlantısı ile period bağlantısının karışmadığı doğrulanmalı.
- [ ] Transaction rollback sonrası yarım kayıt kalmadığı doğrulanmalı.
- [ ] Deadlock retry gereken action'da attempts=3 davranışı test edilmeli.
- [ ] Idempotency aynı anahtarla ikinci istekte çift kayıt üretmemeli.
- [ ] Optimistic lock eski version ile overwrite'ı engellemeli.
- [ ] Kapalı ay document_date ile işlem engellemeli.
- [ ] created_at değişse bile document_date kuralı bozulmamalı.
- [ ] Decimal değerler string karşılaştırmalarıyla test edilmeli; float assertion yapılmamalı.
- [ ] CHECK constraint doğrudan DB seviyesinde ihlali reddetmeli.
- [ ] Foreign key aynı period içinde öksüz referansı engellemeli.
- [ ] Master user ID alanı cross-DB constraint oluşturmamalı.
- [ ] Actor name snapshot Master kapalıyken geçmiş kaydı okunur tutmalı.
- [ ] Arama varsa Türkçe normalize search_index ve trigram planı doğrulanmalı.
- [ ] Dosya varsa sahte uzantı/MIME senaryosu reddedilmeli.
- [ ] SVG yükleme reddedilmeli.
- [ ] Export varsa yetkisiz hassas alanı içermemeli.
- [ ] Cache kullanılan yerde çıplak anahtar yerine CacheKey kullanılmalı.
- [ ] Stok/cari/number series cache edilmediği doğrulanmalı.
- [ ] Integrity komutu farkı raporlamalı, otomatik düzeltmemeli.
- [ ] Activity log kritik state change için actor/correlation bilgisi taşımalı.
- [ ] Kod formatı Pint'ten geçmeli.
- [ ] Larastan seviye 6 analizinde yeni hata bırakılmamalı.
- [ ] Pest testi gerçek PostgreSQL üzerinde çalışmalı.
- [ ] Uygulama testinde en az iki company ve iki period context kullanılmalı.
- [ ] Queue varsa session olmadan doğru period'a bağlanmalı.
- [ ] Yeni period erişimi olmayan kullanıcı DB seçememeli.
- [ ] Archived/closed period davranışı açıkça test edilmeli.
- [ ] Yeni migration rollback edilebilir olmalı veya geri dönüş kısıtı açık yazılmalı.
- [ ] Unique constraint yarış koşulunda da veri bütünlüğünü korumalı.
- [ ] Validation yalnız UI'da değil Action'da kritik iş kuralını korumalı.
- [ ] Türkçe hata mesajı kullanıcıya teknik stack trace göstermemeli.
- [ ] Beklenmeyen hata kullanıcıya correlation code vermeli.
- [ ] Loglarda TC/şifre/token gibi hassas veri bulunmamalı.
- [ ] Soft/passive davranış kararla uyumlu olmalı.
- [ ] Dönem devri etkisi varsa carry listesine eklenmeli.
- [ ] Yeni türetilmiş kolon varsa yeniden hesaplama testi bulunmalı.

### Claude kod inceleme matrisi
- [ ] Görevde geçen her tablo için Master mı Period mı olduğu açık.
- [ ] Period migration içinde company_id bulunmuyor.
- [ ] Period modelinde BelongsToCompany/global scope bulunmuyor.
- [ ] Master kullanıcıya period FK kurulmamış.
- [ ] Aynı period ilişkilerinde gereksiz uygulama-level exists yerine FK mevcut.
- [ ] Cross-company kaynak alanları scalar ve nullable.
- [ ] Dönem devri etkilenen yeni kart/türetilmiş kaydı kapsıyor.
- [ ] ID/kod sürekliliği aynı şirket yıl devrinde korunuyor.
- [ ] Şirketler arası kopyada hedef ID'nin yeni olduğu varsayılıyor.
- [ ] Kullanıcı erişimi company_user + period_user_access ile ayrılıyor.
- [ ] document_date ve created_at semantiği karıştırılmamış.
- [ ] Money aritmetiğinde float/cast kullanılmamış.
- [ ] DB decimal hassasiyetleri kararlarla aynı.
- [ ] Transaction sınırı Action içinde.
- [ ] lockForUpdate yalnız gerekli kritik sayaç/bakiye satırlarında.
- [ ] Idempotency state changing entry point'te.
- [ ] version update where version=old biçiminde korunuyor.
- [ ] DomainException kullanıcı hatası olarak ele alınıyor.
- [ ] Unexpected exception correlation id ile loglanıyor.
- [ ] Audit actor snapshot içeriyor.
- [ ] cost.view veriyi üretim seviyesinde engelliyor.
- [ ] Dosya güvenlik kuralları varsa MIME/UUID/SVG kuralı mevcut.
- [ ] Cache edilen veri doğruluk açısından güvenli.
- [ ] Stok/cari bakiyesi cache edilmemiş.
- [ ] Arama alanı normalize search_index kullanıyor.
- [ ] Trigram index gerekiyorsa migration'da oluşturuluyor.
- [ ] CHECK constraint uygulama validation'ını tamamlıyor.
- [ ] Integrity kontrolü otomatik düzeltme yapmıyor.
- [ ] Pest senaryoları gerçek PostgreSQL gerektiriyor.
- [ ] İki ayrı period DB ile veri sızıntısı testi var.
- [ ] Queue kodu session'a bağımlı değil.
- [ ] UI yeni kayıt ekranında örnek/başka kayıt verisi göstermiyor.
- [ ] Liste ilk satırı varsayılan seçili açılmıyor.
- [ ] UI tarafında belge numarası üretilmiyor.
- [ ] Yeni CSS dosyası/Tailwind/Filament eklenmiyor.
- [ ] Kabul ölçütü yalnız kodun varlığını değil davranışı doğruluyor.
- [ ] Test expected değerleri aynı production fonksiyonundan hesaplanmıyor.
- [ ] Migration adı/connection hedef DB ile uyumlu.
- [ ] Rollback/cleanup test verisini başka period'a taşımıyor.
- [ ] Görev dışı refactor yapılmıyor.

## Kabul ölçütü
```bash
php artisan migrate --database=master --path=database/migrations/master
php artisan db:seed
psql -l | grep -E "ABCHolding_2026|XYZltd_2026"   # ikisi de var
```
- Aynı şirket+yıl ikinci kez oluşturulamıyor
- `db_prefix` değiştirilmeye çalışılınca reddediliyor


### Ek Claude doğrulama senaryoları
- [ ] 01. Bu görevin ürettiği her sorguda beklenen connection adını doğrula.
- [ ] 02. Yeni modelin base class seçimini testte açıkça assert et.
- [ ] 03. Migration şemasında karar günlüğüne aykırı legacy kolon olmadığını kontrol et.
- [ ] 04. Hata yolunda transaction'ın bütün yan etkileri geri aldığını doğrula.
- [ ] 05. İkinci kullanıcı/eşzamanlı istek senaryosunda sessiz overwrite olmadığını doğrula.
- [ ] 06. Aynı işlemin retry edilmesi çift kayıt üretmemeli.
- [ ] 07. Aktif period değiştirildiğinde önceki period model sorgusunun sızmadığını doğrula.
- [ ] 08. Master erişimi iptal edilmiş kullanıcı yeni period isteği başlatamamalı.
- [ ] 09. Audit kaydının iş kaydıyla aynı semantik actor bilgisini taşıdığını doğrula.
- [ ] 10. Kabul testinde veriyi production helper ile değil bağımsız beklenen değerle karşılaştır.
- [ ] 11. Yeni alanın null/default davranışını açık test et.
- [ ] 12. Unique/FK/CHECK hatalarının kullanıcıya DomainException/validation olarak çevrildiğini doğrula.
- [ ] 13. Kuyruk varsa serialize edilen bağlamın company ve period kimliğini koruduğunu doğrula.
- [ ] 14. İşlem sonunda yanlış connection'ın açık bırakılmadığını doğrula.
- [ ] 15. Dışa aktarma veya API çıktısı varsa yetkisiz alan sızıntısı olmadığını doğrula.
- [ ] 16. Arşiv period salt-okunur davranışının bu göreve etkisini kontrol et.
- [ ] 17. Dönem devri yeni tabloyu taşıyacaksa G-1110 kapsamına etkisini not et.
- [ ] 18. Yeni türetilmiş alanın integrity komutuna dahil olduğunu doğrula.
- [ ] 19. Schema rollback sonrası tekrar migrate edilebilir olduğunu doğrula.
- [ ] 20. Pint ve Larastan sonuçlarında görev kaynaklı yeni hata olmadığını doğrula.
- [ ] 21. Bu görevin ürettiği her sorguda beklenen connection adını doğrula.
- [ ] 22. Yeni modelin base class seçimini testte açıkça assert et.
- [ ] 23. Migration şemasında karar günlüğüne aykırı legacy kolon olmadığını kontrol et.
- [ ] 24. Hata yolunda transaction'ın bütün yan etkileri geri aldığını doğrula.
- [ ] 25. İkinci kullanıcı/eşzamanlı istek senaryosunda sessiz overwrite olmadığını doğrula.
- [ ] 26. Aynı işlemin retry edilmesi çift kayıt üretmemeli.
- [ ] 27. Aktif period değiştirildiğinde önceki period model sorgusunun sızmadığını doğrula.
- [ ] 28. Master erişimi iptal edilmiş kullanıcı yeni period isteği başlatamamalı.
- [ ] 29. Audit kaydının iş kaydıyla aynı semantik actor bilgisini taşıdığını doğrula.
- [ ] 30. Kabul testinde veriyi production helper ile değil bağımsız beklenen değerle karşılaştır.
- [ ] 31. Yeni alanın null/default davranışını açık test et.
- [ ] 32. Unique/FK/CHECK hatalarının kullanıcıya DomainException/validation olarak çevrildiğini doğrula.
- [ ] 33. Kuyruk varsa serialize edilen bağlamın company ve period kimliğini koruduğunu doğrula.
- [ ] 34. İşlem sonunda yanlış connection'ın açık bırakılmadığını doğrula.
- [ ] 35. Dışa aktarma veya API çıktısı varsa yetkisiz alan sızıntısı olmadığını doğrula.
- [ ] 36. Arşiv period salt-okunur davranışının bu göreve etkisini kontrol et.
- [ ] 37. Dönem devri yeni tabloyu taşıyacaksa G-1110 kapsamına etkisini not et.
- [ ] 38. Yeni türetilmiş alanın integrity komutuna dahil olduğunu doğrula.
- [ ] 39. Schema rollback sonrası tekrar migrate edilebilir olduğunu doğrula.
- [ ] 40. Pint ve Larastan sonuçlarında görev kaynaklı yeni hata olmadığını doğrula.
- [ ] 41. Bu görevin ürettiği her sorguda beklenen connection adını doğrula.
- [ ] 42. Yeni modelin base class seçimini testte açıkça assert et.
- [ ] 43. Migration şemasında karar günlüğüne aykırı legacy kolon olmadığını kontrol et.
- [ ] 44. Hata yolunda transaction'ın bütün yan etkileri geri aldığını doğrula.
- [ ] 45. İkinci kullanıcı/eşzamanlı istek senaryosunda sessiz overwrite olmadığını doğrula.
- [ ] 46. Aynı işlemin retry edilmesi çift kayıt üretmemeli.
- [ ] 47. Aktif period değiştirildiğinde önceki period model sorgusunun sızmadığını doğrula.
- [ ] 48. Master erişimi iptal edilmiş kullanıcı yeni period isteği başlatamamalı.
- [ ] 49. Audit kaydının iş kaydıyla aynı semantik actor bilgisini taşıdığını doğrula.
- [ ] 50. Kabul testinde veriyi production helper ile değil bağımsız beklenen değerle karşılaştır.
- [ ] 51. Yeni alanın null/default davranışını açık test et.
- [ ] 52. Unique/FK/CHECK hatalarının kullanıcıya DomainException/validation olarak çevrildiğini doğrula.
- [ ] 53. Kuyruk varsa serialize edilen bağlamın company ve period kimliğini koruduğunu doğrula.
- [ ] 54. İşlem sonunda yanlış connection'ın açık bırakılmadığını doğrula.
- [ ] 55. Dışa aktarma veya API çıktısı varsa yetkisiz alan sızıntısı olmadığını doğrula.
- [ ] 56. Arşiv period salt-okunur davranışının bu göreve etkisini kontrol et.
- [ ] 57. Dönem devri yeni tabloyu taşıyacaksa G-1110 kapsamına etkisini not et.
- [ ] 58. Yeni türetilmiş alanın integrity komutuna dahil olduğunu doğrula.
- [ ] 59. Schema rollback sonrası tekrar migrate edilebilir olduğunu doğrula.
- [ ] 60. Pint ve Larastan sonuçlarında görev kaynaklı yeni hata olmadığını doğrula.
- [ ] 61. Bu görevin ürettiği her sorguda beklenen connection adını doğrula.
- [ ] 62. Yeni modelin base class seçimini testte açıkça assert et.
- [ ] 63. Migration şemasında karar günlüğüne aykırı legacy kolon olmadığını kontrol et.
- [ ] 64. Hata yolunda transaction'ın bütün yan etkileri geri aldığını doğrula.
- [ ] 65. İkinci kullanıcı/eşzamanlı istek senaryosunda sessiz overwrite olmadığını doğrula.
- [ ] 66. Aynı işlemin retry edilmesi çift kayıt üretmemeli.
- [ ] 67. Aktif period değiştirildiğinde önceki period model sorgusunun sızmadığını doğrula.
- [ ] 68. Master erişimi iptal edilmiş kullanıcı yeni period isteği başlatamamalı.
- [ ] 69. Audit kaydının iş kaydıyla aynı semantik actor bilgisini taşıdığını doğrula.
- [ ] 70. Kabul testinde veriyi production helper ile değil bağımsız beklenen değerle karşılaştır.
- [ ] 71. Yeni alanın null/default davranışını açık test et.
- [ ] 72. Unique/FK/CHECK hatalarının kullanıcıya DomainException/validation olarak çevrildiğini doğrula.
- [ ] 73. Kuyruk varsa serialize edilen bağlamın company ve period kimliğini koruduğunu doğrula.
- [ ] 74. İşlem sonunda yanlış connection'ın açık bırakılmadığını doğrula.
- [ ] 75. Dışa aktarma veya API çıktısı varsa yetkisiz alan sızıntısı olmadığını doğrula.
- [ ] 76. Arşiv period salt-okunur davranışının bu göreve etkisini kontrol et.
- [ ] 77. Dönem devri yeni tabloyu taşıyacaksa G-1110 kapsamına etkisini not et.
- [ ] 78. Yeni türetilmiş alanın integrity komutuna dahil olduğunu doğrula.
- [ ] 79. Schema rollback sonrası tekrar migrate edilebilir olduğunu doğrula.
- [ ] 80. Pint ve Larastan sonuçlarında görev kaynaklı yeni hata olmadığını doğrula.
- [ ] 81. Bu görevin ürettiği her sorguda beklenen connection adını doğrula.
- [ ] 82. Yeni modelin base class seçimini testte açıkça assert et.
- [ ] 83. Migration şemasında karar günlüğüne aykırı legacy kolon olmadığını kontrol et.

## İstem
> companies ve periods tabloları için master migration'larını, Company ve
> Period modellerini, CreatePeriod action'ını ve CompanySeeder'ı yaz.
> Şemaları belirtilen dosyalardan birebir al. CreatePeriod veritabanını
> oluşturup migration çalıştırsın, hata durumunu try/catch ile ele alsın.
