# DOC2 — Çok şirketli, dönemsiz MarsProject

## Ürün kararı (2026-10-11)

DOC2 çok şirketlidir; şirketlerin finansal ve operasyonel geçmişi yıl bazında fiziksel veritabanlarına bölünmez.
Master kimlik, şirket, rol ve çapraz şirket yönetişim verilerini tutar. Her şirkete **bir kalıcı**
PostgreSQL iş veritabanı ayrılır. Örnek: `MarsProject_Master`, `mars_company_1`, `mars_company_2`.
Bu, mevcut şirket/yıl period veritabanlarından farklıdır. Takvim yılı yalnız belge tarihi, muhasebe
filtreleri, numaralandırma ve raporlar için bir alan olarak kalabilir; aktif dönem seçimi veya dönem
devri iş kuralı **yoktur**.

## Güvenli uygulama durumu

**DOC2 henüz aktif değil.** `MARS_ARCHITECTURE=legacy` (varsayılan) ile mevcut eski kod çalışmaya
devam eder. `MARS_ARCHITECTURE=doc2` mevcut aşamada uygulama başlangıcını kasten engeller;
dönem bağımlı Action'lar, queue işler, backup/restore ve migration'lar dönüşmeden açılması
veri karışmasına veya yanlış DB kullanımına neden olabilir.

İkinci blok: Master tarafında `company_database_registry` tablosu, doğrulama ve aktivasyon kapısı ile
`php artisan doc2:plan --json` salt okunur eski şirket/yıl DB envanteri eklendi.
Kayıt `active` durumunda, doğrulama/aktivasyon zamanları dolu ve hedef DB adı şirket kimliğiyle
eşleşmiyorsa şirket bağlantısı açılmaz. Bu aşamada kayıt oluşturulmaz, hedef fiziksel DB oluşturulmaz.

İlk kod bloğu: `company` isimli başlangıçta boş PostgreSQL bağlantısı,
`CompanyDatabaseName` adlı sabit şirket DB adlandırması, `CompanyDatabaseContext` ile
Master `company_user` kontrolü, yetki gerektirmeyen sistem/queue akışlarının ayrı API'si
ve `CompanyModel` erişim koruması. Bunların **hiçbiri mevcut DB'leri oluşturmaz, silmez,
yeniden adlandırmaz veya bağlam değişimini production'da açmaz.**

## Geçişin engelleri ve işleri

1. **Master metadata:** `companies` için şirket DB kayıt/versiyon durumu; eski `periods`
   kayıtlarının read-only envanteri. Şema geçişinde eski bağlantılar korunur, destructive `down` yoktur.
2. **Tek şirket veritabanı migration'ı:** eski `database/migrations/period` tablolarının
   şirket başına bir kez kurulabilir hale getirilmesi; `posting_periods`, dönem devri ve
   `period_document_carries` gibi yıllık kavramların analizi; hiçbir eski yıl verisi kaybolmaz.
3. **Data consolidation:** aynı şirkete ait birden çok fiziksel yıllık DB'yi kalıcı DB içinde
   anahtar/FK ve ID çakışması çözerek birleştirme. Eski→yeni ID, belge ve kaynak provenance
   eşleme tabloları; önce prova ve karşılaştırmalı sayım/toplam, sonra kesinti penceresi.
4. **Context ve yetki:** `PeriodContext`, `SourcePeriodContext`, `PeriodModel`,
   `period_user_access`, `PeriodPermissionContext` ve tüm HTTP/Livewire/queue/console
   kullanımının `CompanyDatabaseContext` ve şirket temelli izinlerle değiştirilmesi.
   Yetkisiz kullanıcı başka şirketin fiziksel DB'sine erişemez.
5. **Modüller:** 181 Action, 89 Livewire, 89 Model ve diğer jobs/commands tek tek
   `period_id`, `period.year`, kaynak/target DB, status/read-only ve carry bağımlılığı
   açısından dönüştürülür; hiçbir iş davranışı sessizce silinmez.
6. **Sihirbaz:** `/kurulum` 8 adım dönem/yıl olmaksızın şirket + yetki + yönetici + depo
   + kayıt başlangıcı olarak yeniden düzenlenir; `CREATE DATABASE` yetkili, ayrı
   provisioning işleminin sorumluluğundadır.
7. **Operasyonlar:** şirket DB listesi üzerinden backup/restore, health, schema migration,
   audit, numaralandırma, rapor, cache, queue, dış kanal ve müşteri erişim izolasyonu.
8. **Test:** DOC2'ye özgü Unit/Contracts/Feature/Integration/Valkey/Playwright
   ve şirketler arası saldırı/rollback senaryoları. Eski TEST V4 dönem testleri
   DOC2 geçerliliği için sonuç sayılmaz.
9. **Go-live:** onaylı test/restore provası, production dışı setup, gerçek kullanıcı
   live UAT ve mutabakat sonrasında ayrıca dağıtım kararı. Eski DB'ler backup ve
   doğrulama süreci boyunca silinmez.

## Denetim komutu

`php artisan doc2:audit` — sadece kodu tarar; DB bağlantısı, SQL, migration ve
sistem servisi değişikliği yoktur. `--strict` kalan dönem bağımlılıklarını CI için
başarısızlık olarak işaretler. Referans sayıları bire bir modül/test sayısı değildir.

## Master kaynak envanteri

`php artisan doc2:plan` salt okunur Master bağlantısı gerektirir; şirket başına kaynak eski
dönem veritabanlarını, hedef sabit veritabanı adını ve geçiş sicili durumunu listeler.
`--json` rapor üretir, `--company=ID` seçili şirkete sınırlar. Bu komut **hiçbir
veriyi taşımaz**. `company_database_registry` migration'ı ekleyicidir ve
otomatik rollback ile silme bloke edilmiştir. Migration henüz çalıştırılmamıştır.

## Üretime müdahale yasağı

CI runner'ına PostgreSQL/Valkey sunucusu kurulmaz. PostgreSQL ve Valkey'in mevcut
PROD VM servislerine yalnız güvenli ve izole test/iş bağlantıları yapılır.
DOC2 birleşimi için backup doğrulaması, veri taşıma planı ve açık ayrı go-live kararı gerekir.
