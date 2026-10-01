# G-003 — Bağlantı yönetimi (master + dönem)

## Amaç
Sistemin kalbi. Kullanıcı şirket ve dönem seçer, uygulama o veritabanına
bağlanır. **Bu görev yanlış yapılırsa hiçbir şey çalışmaz.**

## Önkoşul
G-002 (companies + periods)

## Dokunulacak dosyalar
- `config/database.php`
- `app/Support/Period/PeriodContext.php`
- `app/Models/MasterModel.php`
- `app/Models/PeriodModel.php`
- `app/Http/Middleware/SetActivePeriod.php`
- `bootstrap/app.php`


## Şema / Kod

Bu görevde aşağıdaki mevcut kod/şema örnekleri normatiftir. Yeni tablo gerekmiyorsa migration ekleme; mevcut mimari sözleşmeyi bozacak ek şema uydurma.

## config/database.php

```php
'master' => [
    'driver'   => 'pgsql',
    'host'     => env('DB_HOST', '127.0.0.1'),
    'database' => env('DB_MASTER_DATABASE', 'MarsProject_Master'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
    'charset'  => 'utf8',
    'search_path' => 'public',
],

'period' => [
    'driver'   => 'pgsql',
    'host'     => env('DB_HOST', '127.0.0.1'),
    'database' => null,                 // çalışma anında doldurulur
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
    'charset'  => 'utf8',
    'search_path' => 'public',
],
```

`'default' => 'master'`

## PeriodContext

```php
namespace App\Support\Period;

final class PeriodContext
{
    public static function use(int $companyId, int $year): Period
    {
        $period = Period::on('master')
            ->where('company_id', $companyId)
            ->where('year', $year)
            ->firstOrFail();

        config(['database.connections.period.database' => $period->database_name]);
        DB::purge('period');
        DB::reconnect('period');

        session(['active_company_id' => $companyId, 'active_year' => $year]);

        return $period;
    }

    public static function companyId(): ?int { return session('active_company_id'); }
    public static function year(): ?int      { return session('active_year'); }

    public static function ensure(): void
    {
        if (! self::companyId() || ! self::year()) {
            throw new NoActivePeriodException('Şirket ve dönem seçilmedi.');
        }
    }
}
```

## Model temel sınıfları

```php
abstract class MasterModel extends Model
{
    protected $connection = 'master';
    // global scope YOK — master'da yalnız şirket üstü tablolar var
}

abstract class PeriodModel extends Model
{
    protected $connection = 'period';
    // company_id YOK, global scope YOK — izolasyon fiziksel
}
```

**Kartlar dahil her iş tablosu `PeriodModel`'den türer.**

## Middleware

`SetActivePeriod`:
1. Giriş yapılmamışsa devam
2. Session'da şirket/dönem yoksa: `users.last_company_id` ve son aktif
   dönem; yoksa seçim ekranına yönlendir
3. Kullanıcı o şirkete `company_user` üzerinden bağlı değilse 403; ayrıca seçilen dönem için `period_user_access` yoksa 403
4. Dönem `archived` ise uyarı ve seçim ekranı
5. `PeriodContext::use($companyId, $year)`

`web` grubuna, `auth`'tan **sonra** eklenir.

## Migration klasörleri

```
database/migrations/master/    ← master tabloları
database/migrations/period/    ← dönem tabloları
```

Komutlar:
```bash
php artisan migrate --database=master --path=database/migrations/master
php artisan migrate --database=period --path=database/migrations/period
```

## Kuyruk işleri

Kuyrukta session yoktur. İş `company_id` ve `year` taşır:

```php
public function handle(): void
{
    PeriodContext::use($this->companyId, $this->year);
    // ...
}
```


## Kurallar

**Faz bağlamı:** Faz 0 — altyapı; sonraki fazların sözleşmesini bozmamalı.

### Bu göreve özel kanonik notlar
- Kalıcı bağlantılar yalnız master ve period; period_source yalnız kopyalama sırasında geçicidir.
- PeriodContext kurulmadan önce company_user + period_user_access kontrol edilir.
- Kartlar period DB'dedir; ayrı company connection oluşturma.

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
- İki şirket, iki dönem oluşturuluyor (4 veritabanı)
- Şirket/dönem değişince sorgular doğru veritabanına gidiyor
- Dönem modellerinde `company_id` kolonu **yok**
- Kart tabloları (contacts, products) dönem veritabanında
- Kuyruk işi doğru veritabanına bağlanıyor
- Dönem seçilmeden dönem modeline erişim `NoActivePeriodException`


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

## İstem
> config/database.php'ye master ve period bağlantılarını ekle.
> PeriodContext, MasterModel, PeriodModel ve SetActivePeriod
> middleware'ini yukarıdaki kodla birebir yaz. Migration klasörlerini
> master ve period olarak ayır. DB::purge ve DB::reconnect satırlarını
> atlama. Başka dosyaya dokunma.
