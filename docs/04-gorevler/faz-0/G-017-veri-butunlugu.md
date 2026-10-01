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


### Uygulama ayrıntıları
- `integrity:all` tek çağrıda ilgili integrity komutlarını çalıştırır; farkı raporlar, otomatik düzeltmez.
- Her integrity komutu kendi kaynak hareketi ile türetilmiş sonucu karşılaştırır; kart referansları period içi FK ile korunur.
- İşletme verisini düzeltmek için integrity komutu doğrudan update yapmaz; fark kullanıcı/operasyon akışına taşınır.
- Yeni türetilmiş alan eklendiğinde aynı görevde ilgili integrity kontrolü de eklenir.

## Kabul ölçütü
- `integrity:all` tüm aktif dönemlerde çalışıyor, bağlam geri yükleniyor
- Elle bozulan bir bakiye (`DB::table` ile) kontrolde yakalanıyor
- Fark yoksa rapor `mismatch_count = 0` yazıyor
- CHECK kısıtı ihlal eden insert veritabanı seviyesinde reddediliyor
- "Yeniden hesapla" bakiyeyi hareket toplamına eşitliyor ve loglanıyor
- Ana sayfa göstergesi eski kontrolde kırmızı oluyor


## İstem
> IntegrityCheck arayüzünü, dört kontrol sınıfını, IntegrityCommand'ı,
> integrity_reports tablosunu ve Bütünlük Kontrolü ekranını yaz.
> integrity:all tüm aktif dönemleri dolaşsın ve sonunda eski bağlamı
> geri yüklesin. Otomatik düzeltme YAPMA. CHECK kısıtlarını
> 16-veri-butunlugu.md içindeki SQL'e göre migration'lara ekle.
