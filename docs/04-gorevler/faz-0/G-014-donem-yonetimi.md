# G-014 — Dönem oluşturma ve seçme

## Amaç
Kullanıcının şirket ve dönem değiştirebilmesi, yeni dönem açabilmesi.

## Önkoşul
G-002, G-003, G-005


## Dokunulacak dosyalar
- `app/Livewire/Pages/Settings/Periods.php`
- `app/Livewire/Components/PeriodSwitcher.php`
- `app/Actions/Periods/CreatePeriod.php` entegrasyonu
- period close/reopen Action'ları
- period policy/permission kontrolleri
- `tests/Feature/Period/PeriodManagementTest.php`


## Şema / Kod

Bu görev yeni tablo gerektirmiyorsa migration ekleme. Mevcut kod örnekleri ve aşağıdaki mimari sözleşme normatiftir.

## Ekranlar

### Şirket / dönem seçici (üst çubuk)
İki açılır liste: şirket ve yıl. Şirket değişince yıl listesi yenilenir.
Yalnızca kullanıcının `company_user` üzerinden bağlı olduğu şirketler.
Seçim `PeriodContext::use()` çağırır, `users.last_company_id` günceller,
sayfayı yeniler.

Aktif dönem üst çubukta **her zaman görünür** — kullanıcı hangi yılda
çalıştığını bilmeli. Kapalı dönemdeyse uyarı rengiyle gösterilir.

### Dönemler ekranı (Ayarlar)
Liste: şirket, yıl, veritabanı adı, durum, devir yapıldı mı, boyut.
Eylemler: **Yeni Dönem Aç**, **Dönemi Kapat**, **Devir Yap** (Faz 11b).

## Yeni dönem açma
1. Şirket ve yıl seçilir
2. Uyarı: "Bu işlem yeni bir veritabanı oluşturur"
3. `CreatePeriod` çağrılır
4. Sonuç: başarılıysa döneme geçilir, değilse hata ve temizlik talimatı

## Kurallar
- Dönem açma/kapatma ilgili permission ile korunur; yeniden açma ayrı permission + zorunlu gerekçe ister
- Kapalı dönem seçilebilir ama salt okunurdur; kayıt girişi engellenir
- Devir yapılmamış dönemde açılış bakiyesi yoktur — ekranda uyarı
- Arşivlenmiş dönem seçilemez, önce geri yüklenmeli


**Faz bağlamı:** Faz 0 — altyapı.

### Göreve özel kararlar
- Dönem kaydı Master'dadır; period DB ayrı fiziksel veritabanıdır.
- Yeni dönem açıldığında erişim otomatik verilmez; devir sonunda kopyalama sorusu ayrı akıştır.
- Kapanmış dönem salt-okunur; yeniden açma ayrı izin + gerekçe.


### Uygulama ayrıntıları
- Period kaydı Master DB'de company_id + year + database_name + status metadata'sını tutar.
- Yeni dönem oluşturma fiziksel DB oluşturma ve period migration sürecini tetikleyebilir; business kartlarını kendiliğinden kopyalamaz.
- Kapanmış period varsayılan read-only'dir; yeniden açma ayrı izin + gerekçe gerektirir.
- Dönem devri ayrı Faz 11b akışıdır; bu görev yalnız period yaşam döngüsü yönetimini kurar.

## Kabul ölçütü
- Yeni dönem açılıyor, veritabanı oluşuyor, migration çalışıyor
- Dönem değişince veriler o döneme ait geliyor
- Kapalı dönemde kayıt girişi engelleniyor
- `periods.create` izni olmayan kullanıcı dönem açamıyor
- Aktif dönem üst çubukta görünüyor


## İstem
> Şirket/dönem seçici bileşenini ve Dönemler yönetim ekranını yaz.
> Seçici üst çubukta olsun, aktif dönemi her zaman göstersin.
> Yeni dönem açma CreatePeriod action'ını çağırsın. Kapalı dönemde
> kayıt girişini engelle. Yeni dönem oluşturma `periods.create` izni gerektirsin; kapanmış dönemi yeniden açma ise ayrı yeniden-açma izni + gerekçe ile yapılsın.
