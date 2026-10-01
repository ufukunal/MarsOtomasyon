# G-012 — Uygulama kabuğu ve tema iskeleti

## Amaç
Giriş ekranı, ana yerleşim (sol menü + üst şerit + içerik), şirket seçici ve
**tek tema dosyası**. Bileşen kütüphanesi Faz 0b'de yazılacak; burada yalnız
iskelet kurulur.

## Önkoşul
G-003, G-005

## Dokunulacak dosyalar
- `resources/views/layouts/app.blade.php`
- `resources/views/livewire/shell/company-switcher.blade.php`
- `resources/css/app.css`  ← **TEK tema dosyası**
- `resources/js/app.js`    ← asgari


## Şema / Kod

Bu görevde aşağıdaki mevcut kod/şema örnekleri normatiftir. Yeni tablo gerekmiyorsa migration ekleme; mevcut mimari sözleşmeyi bozacak ek şema uydurma.

## Yerleşim

```
┌──────────┬────────────────────────────────────┐
│          │  üst şerit: arama · şirket · kullanıcı │
│ sol menü ├────────────────────────────────────┤
│          │  sayfa başlığı + eylemler          │
│          ├────────────────────────────────────┤
│          │  içerik                            │
└──────────┴────────────────────────────────────┘
```

## Tema değişkenleri

`resources/css/app.css` şununla başlar:

```css
:root{
  --bg:#f2f5f7; --surface:#ffffff; --soft:#f8fafb;
  --line:#d7e0e5; --line2:#e9eef1;
  --text:#263741; --muted:#75858d;
  --primary:#1f8ac8; --primary-600:#1673aa;
  --green:#0aa56a; --red:#e64e43; --amber:#d38c00;
  --side:252px; --top:48px; --tabs:34px; --radius:0;
  --ctl:30px; --fs:12px;
}
```

Bu değerler prototipin görünümünden alınmıştır; tema kararı Faz 0b'de
ekran üzerinden kesinleşecek.

## Sol menü

Menü **veriden** üretilir (`config/navigation.php`), koda gömülmez.
Kullanıcının yetkisi olmayan madde **hiç basılmaz**.

Faz 0'da yalnızca Ayarlar grubu: Şirketler, Kullanıcılar, Roller,
Dönemler, İşlem Geçmişi, Yazdırma Profilleri, Şirket Bağlantıları.

## Şirket seçici

Üst şeritte açılır liste. Yalnızca kullanıcının `company_user` üzerinden
bağlı olduğu şirketler listelenir. Seçim `session('active_company_id')`
ve `users.last_company_id` günceller, sayfayı yeniler.

## Kurallar
- **Ekran bazında CSS dosyası açılmaz.** Eksik olan temaya eklenir.
- JS yalnızca gerçekten tarayıcıda olması gereken için yazılır.
- Tüm metinler `lang/tr/` üzerinden.


**Faz bağlamı:** Faz 0 — altyapı; sonraki fazların sözleşmesini bozmamalı.

### Bu göreve özel kanonik notlar
- Filament/Tailwind ekleme; Livewire + tek düz CSS kullan.
- v65 UI referansının görsel dilini izle, business kararını HTML'den türetme.


### Uygulama ayrıntıları
- Uygulama kabuğu Livewire 3 + tek düz CSS tema ile hazırlanır; Filament/Tailwind eklenmez.
- v65 yalnız görsel dil, navigasyon ve terminoloji referansıdır; business kuralı HTML'den türetilmez.
- Company/period seçici Master erişim bilgisine dayanır ve aktif context'i görünür kılar.
- Yeni ekranlar prototipteki boş-form davranışını korur; örnek kayıtla dolu açılmaz.

## Kabul ölçütü
- Giriş yapılır, ana sayfa açılır
- Şirket değiştirilir, menü ve veri o şirkete geçer
- Yetkisiz kullanıcıda menü maddeleri görünmez
- `resources/css` altında **tek** dosya vardır


## İstem
> Ana yerleşim blade dosyasını, şirket seçici Livewire bileşenini ve
> resources/css/app.css tema dosyasını yaz. Menü config/navigation.php'den
> üretilsin, yetkisiz maddeler basılmasın. Sol menü + üst şerit + içerik
> düzeni kurulsun. Ekran bazında ayrı CSS dosyası AÇMA.
