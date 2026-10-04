# MarsOtomasyon

Avize toptan ticareti için firmaya özel ERP. Ön muhasebe, cari, kasa/banka/çek-senet, stok, satış, alış, iade, ithalat, basit üretim, fason ve e-ticaret entegrasyonlarını kapsar.

## Kaynak önceliği

1. Kullanıcının son açık kararı
2. `GUNCELLEME-PROMPT.md`
3. `docs/05-karar-gunlugu/kararlar.md`
4. `docs/00-genel/07-veritabani-mimarisi.md`
5. Güncel veri modeli / iş kuralı / görev belgeleri
6. `DEVIR-PROMPT.md` — üsttekilerle çelişmeyen kısımlar
7. `reference/marsotomasyon-PROTOTIP-ONAYLI-v65.html` — UI/terminoloji/görsel referans

## Güncel mimari

- PHP ^8.4, Laravel 13, Livewire 3.
- PostgreSQL: `MarsProject_Master` + her şirket/yıl için ayrı fiziksel period DB.
- Kartlar dahil yıllık işletme verileri period DB'dedir; period tablolarında `company_id` yoktur.
- Aynı period DB içindeki ilişkiler gerçek FK kullanır; Master ↔ Period arasında cross-database FK kurulmaz.
- Redis/Valkey session, cache ve queue altyapısında kullanılır.
- Mevcut period DB'leri `migrate:periods` ile toplu güncellenir.

Repo aktif Laravel uygulama kodunu ve kanonik dokümantasyonu birlikte içerir.

## Güncel durum

Faz 0, Faz 1, Faz 2, Faz 3, Faz 4, Faz 5, Faz 6 ve Faz 7 production kodu uygulanmıştır. Faz 3; ortak belge şeması, belge hesap motoru, posting zinciri, teklif, satış siparişi ve rezervasyon, irsaliye/kısmi sevk, satış faturası/kısmi fatura, proforma, tahsilat/cari hareket, araçtan sıcak satış ve ters kayıt akışlarını kapsar. Faz 4; satınalma siparişi onayı, kısmi mal kabul, alış faturası, üçlü eşleştirme, hareketli ortalama maliyet güncelleme ve ±%25 alış fiyat sapma uyarısı, ödeme, tedarikçi performansı ve alış integrity kontrollerini kapsar. Faz 5; kasa/banka hesapları ve bakiyeleri, manuel finans hareketleri, virman, gider, avans, çek/senet ve bordro akışları, CSV/XLSX/MT940 banka ekstresi içe aktarma, duplicate koruması, mutabakat, finans ters kayıtları ve `integrity:finance` kontrolünü kapsar. Faz 6; satış ve alış iadeleri, kısmi iade sınırları, satış iadesinin doğrudan karantinaya alınması, karantinadan bağımsız cari finansal etki, iade ters kayıtları, İade Merkezi ve `integrity:returns` kontrolünü kapsar. Faz 7; ithalat dosyası, konteyner ve koli/ürün eşleştirme, değer/ağırlık/hacim bazlı landed-cost dağıtımı, stoğa giriş tarihinde kur kilidi, hareketli ortalama stok girişi, ürün `import_cost` snapshotı, dosya/konteyner kârlılık raporları ve `integrity:imports` kontrolünü kapsar. Faz 4–7 test paketleri ile tüm kalite komutları final local runner turunda toplu çalıştırılacaktır. Faz 8 production geliştirmesi henüz başlatılmamıştır.

K-038 gereği state-changing Livewire istekleri ilk component snapshot'ında üretilen ve retry boyunca sabit kalan idempotency key taşır. Period mutationları period DB'deki, Master mutationları Master DB'deki idempotency kayıtlarını kullanır.

Dönem devrinde K-256 gereği açık `sales_order` ve `purchase_order` yalnız kalan miktarlarıyla yeni period'da yeni confirmed snapshot olarak oluşturulur; aktif satış rezervasyonları location bazında yeniden kurulur ve açık quarantine taşınır.

## Yerel kurulum

Gereksinimler: PHP 8.4, Composer, Node.js/npm, PostgreSQL ve Redis/Valkey.

```bash
cp .env.example .env
composer install
npm ci
php artisan key:generate
```

`.env` içinde Master PostgreSQL bağlantısını ve Redis/Valkey bağlantısını yerel ortama göre ayarla. `DB_MASTER_DATABASE` ile belirtilen Master veritabanını PostgreSQL'de oluşturduktan sonra:

```bash
php artisan migrate --database=master --path=database/migrations/master --force
npm run build
php artisan serve
```

İlk gerçek kurulum `/kurulum` Setup Wizard üzerinden yapılır. Wizard şirketi ve ilk fiziksel period DB'yi oluşturur, period migration zincirini çalıştırır ve başlangıç erişimini hazırlar. Mevcut period veritabanlarını repo şemasına taşımak için:

```bash
php artisan migrate:periods
php artisan permissions:sync-company-roles
```

`permissions:sync-company-roles` production-safe sistem rol senkronizasyonudur; yeni ekran izinlerini mevcut şirketlerin Yönetici/Muhasebe/Satış/Satınalma/Depo/Üretim/Görüntüleyici sistem rollerine uygular. Production'da demo seed kullanılmaz.

## Yerel demo seed

Demo şirket/kullanıcı seed'leri production ortamında çalışmaz. Local veya testing ortamında demo admin oluşturmak için `DEMO_ADMIN_PASSWORD` en az 12 karakter olarak açıkça verilmelidir; sabit varsayılan parola yoktur. E-posta `DEMO_ADMIN_EMAIL` ile değiştirilebilir.

## Kalite kontrolleri

GitHub Quality workflow'unun yerel karşılıkları:

```bash
npm run build
vendor/bin/pint
vendor/bin/phpstan analyse --no-progress
php artisan test --display-warnings
vendor/bin/pest -c phpunit.stock.xml --coverage --min=90
```

Pint sonrasında çalışma ağacında beklenmeyen format farkı kalmamalıdır. Test altyapısı gerçek PostgreSQL kullanır; SQLite test yolu yoktur.

## Backup bildirimleri

Backup e-posta bildirimleri `BACKUP_NOTIFICATION_EMAIL` ile yapılandırılır. Local/testing için `backup@mars.test` sentinel adresidir; production bu sentinel, boş veya geçersiz adresle boot etmez. Gerçek production recipient deployment ortamında verilmelidir.
