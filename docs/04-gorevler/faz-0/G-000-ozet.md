# Faz 0 — özet ve sıra

Faz 0 bittiğinde elinde **çalışan, güvenli bir iskelet** olur: şirketler
izole, yetkiler işliyor, numaralar çakışmıyor, dönem kilitleniyor, her
değişiklik loglanıyor, dosya eklenebiliyor, yedek alınıyor.

Ekran yok denecek kadar azdır — bu normaldir. Faz 0 temeldir, vitrin değil.

## Sıra

| No | Görev | Neden bu sırada |
|---|---|---|
| G-001 | Proje iskeleti | Her şeyin önünde |
| G-002 | companies + **periods** | Bağlantı yönetiminin dayandığı tablolar |
| G-003 | **Bağlantı yönetimi (master + dönem)** | En kritik görev |
| G-004 | company_copy_permissions | Kopyalama izni |
| G-005 | Kullanıcı, rol, izin | Diğer ekranlar buna dayanır |
| G-006 | Numaralandırma | Belgelerden önce hazır olmalı |
| G-007 | Dönem kilidi | Belgelerden önce hazır olmalı |
| G-008 | İşlem geçmişi | Modeller oluştukça eklenmeli |
| G-009 | Dosya ekleri | Her yerde kullanılacak |
| G-010 | Yazdırma profilleri | Sonradan eklemek pahalı |
| G-011 | **İzolasyon testleri** | Atlanamaz |
| G-012 | Kabuk ve tema | Görsel iskelet |
| G-013 | Yedekleme ve kuyruk | **Master her yedekte olmalı** |
| G-014 | Dönem oluşturma ve seçme ekranı | Kullanıcı dönem değiştirebilmeli |
| G-015 | **Giriş ekranı + firma kurulum sihirbazı** | Mimari bunlar olmadan kullanılamaz |
| G-016 | Önbellek altyapısı (dönem bazlı anahtar) | Anahtar dönem taşımazsa veri sızar |
| G-017 | **Veri bütünlüğü altyapısı** | Sessiz bozulma tek kayıtlı sistemde felaket |
| G-018 | **Para aritmetiği, eşzamanlılık, tarih** | Sonradan eklenemez; her hesabı ve formu elden geçirmek gerekir |
| G-019 | **Türkçe arama altyapısı** | PostgreSQL varsayılanı Türkçe'de yanlış sonuç verir |
| G-020 | Hata yönetimi, izleme, güvenlik | — |
| G-021 | `migrate:periods` — çok veritabanlı dağıtım | İlk sürüm güncellemesinde gerekir |

## Faz 0 bitiş ölçütü

- [ ] Master + iki şirket × bir dönem = 3 veritabanı kurulu
- [ ] Dönem verisi fiziksel olarak izole (farklı veritabanı)
- [ ] Master kartları `company_id` ile filtreleniyor
- [ ] Şirket **ve dönem** seçici çalışıyor, yetkisiz şirkete geçilemiyor
- [ ] Yedi rol ve izinler tanımlı, `cost.view` ayrı
- [ ] Numara üretimi kilitli ve boşluksuz
- [ ] Kapalı döneme kayıt girilemiyor
- [ ] Her değişiklik `activity_log`'a düşüyor
- [ ] Dosya eklenebiliyor, disk soyutlaması çalışıyor
- [ ] `PrintManager::send()` tek giriş noktası
- [ ] Tüm testler yeşil, Pint ve Larastan temiz
- [ ] Giriş ve şirket/dönem seçimi çalışıyor
- [ ] Kurulum sihirbazı baştan sona yeni firma kuruyor
- [ ] Önbellek anahtarları şirket ve dönem taşıyor
- [ ] CHECK kısıtları kurulu, ihlal eden insert reddediliyor
- [ ] `integrity:all` çalışıyor ve fark bulmuyor
- [ ] `integrity:files` yazıldı (kayıt ↔ disk eşleşmesi)
- [ ] Tutarlar float değil, `Money` + BCMath ile hesaplanıyor
- [ ] Çift gönderimde tek belge oluşuyor (istek anahtarı)
- [ ] Eşzamanlı düzenlemede ikinci kullanıcı uyarı alıyor (`version`)
- [ ] Dönem aralığı dışı tarihli belge yazılamıyor
- [ ] `oztas` araması `Öztaş` buluyor, trigram indeksi kurulu
- [ ] `/saglik` çalışıyor, SVG yüklemesi reddediliyor
- [ ] `migrate:periods` tüm dönemleri güncelliyor
- [ ] Yedek alınıyor ve **geri yükleme denendi**

Bu maddelerin hepsi işaretlenmeden Faz 0b'ye geçilmez.
