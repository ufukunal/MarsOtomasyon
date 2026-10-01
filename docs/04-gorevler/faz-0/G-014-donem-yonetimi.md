# G-014 — Dönem oluşturma ve seçme

## Amaç
Kullanıcının şirket ve dönem değiştirebilmesi, yeni dönem açabilmesi.

## Önkoşul
G-002, G-003, G-005

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
- Yalnızca **Yönetici** dönem açabilir/kapatabilir
- Kapalı dönem seçilebilir ama salt okunurdur; kayıt girişi engellenir
- Devir yapılmamış dönemde açılış bakiyesi yoktur — ekranda uyarı
- Arşivlenmiş dönem seçilemez, önce geri yüklenmeli

## Kabul ölçütü
- Yeni dönem açılıyor, veritabanı oluşuyor, migration çalışıyor
- Dönem değişince veriler o döneme ait geliyor
- Kapalı dönemde kayıt girişi engelleniyor
- Yönetici olmayan dönem açamıyor
- Aktif dönem üst çubukta görünüyor

## İstem
> Şirket/dönem seçici bileşenini ve Dönemler yönetim ekranını yaz.
> Seçici üst çubukta olsun, aktif dönemi her zaman göstersin.
> Yeni dönem açma CreatePeriod action'ını çağırsın. Kapalı dönemde
> kayıt girişini engelle. Yalnızca Yönetici dönem açabilsin.
