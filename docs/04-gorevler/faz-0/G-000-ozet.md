# Faz 0 — özet ve sıra

Faz 0 bittiğinde elinde **çalışan, güvenli bir iskelet** olur: şirketler
izole, yetkiler işliyor, numaralar çakışmıyor, dönem kilitleniyor, her
değişiklik loglanıyor, dosya eklenebiliyor, yedek alınıyor.

Ekran yok denecek kadar azdır — bu normaldir. Faz 0 temeldir, vitrin değil.

## Sıra

| No | Görev | Neden bu sırada |
|---|---|---|
| G-001 | Proje iskeleti | Her şeyin önünde |
| G-002 | companies | İzolasyonun dayandığı tablo |
| G-003 | **Şirket bağlamı + global scope** | En kritik görev |
| G-004 | company_links | Kopyalama izni |
| G-005 | Kullanıcı, rol, izin | Diğer ekranlar buna dayanır |
| G-006 | Numaralandırma | Belgelerden önce hazır olmalı |
| G-007 | Dönem kilidi | Belgelerden önce hazır olmalı |
| G-008 | İşlem geçmişi | Modeller oluştukça eklenmeli |
| G-009 | Dosya ekleri | Her yerde kullanılacak |
| G-010 | Yazdırma profilleri | Sonradan eklemek pahalı |
| G-011 | **İzolasyon testleri** | Atlanamaz |
| G-012 | Kabuk ve tema | Görsel iskelet |
| G-013 | Yedekleme ve kuyruk | Canlıya çıkmadan şart |

## Faz 0 bitiş ölçütü

- [ ] İki şirket kurulu, veriler birbirinden tamamen izole
- [ ] Şirket seçici çalışıyor, yetkisiz şirkete geçilemiyor
- [ ] Yedi rol ve izinler tanımlı, `cost.view` ayrı
- [ ] Numara üretimi kilitli ve boşluksuz
- [ ] Kapalı döneme kayıt girilemiyor
- [ ] Her değişiklik `activity_log`'a düşüyor
- [ ] Dosya eklenebiliyor, disk soyutlaması çalışıyor
- [ ] `PrintManager::send()` tek giriş noktası
- [ ] Tüm testler yeşil, Pint ve Larastan temiz
- [ ] Yedek alınıyor ve **geri yükleme denendi**

Bu maddelerin hepsi işaretlenmeden Faz 0b'ye geçilmez.
