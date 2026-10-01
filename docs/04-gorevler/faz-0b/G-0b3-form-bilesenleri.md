# G-0b3 — Form, modal ve bildirim

## Amaç
Form alanları, modal, onay kutusu ve bildirim. Tablo dışındaki her şey.

## Önkoşul
G-0b1


## Dokunulacak dosyalar
- Bu görevde tarif edilen mevcut uygulama/migration/test dosyaları; kapsam dışına çıkma.


## Şema / Kod

Bu görev yeni tablo gerektirmiyorsa migration ekleme. Mevcut kod örnekleri ve aşağıdaki mimari sözleşme normatiftir.

## Bileşenler

| Bileşen | Not |
|---|---|
| `x-field.text` | etiket, ipucu, hata, önek/sonek |
| `x-field.number` | miktar/tutar ayrımı, TR biçim, sağa hizalı |
| `x-field.select` | arama kutulu, uzak veri destekli |
| `x-field.lookup` | **cari/ürün seçici** — kod veya ad ile arama, detaylı arama penceresi |
| `x-field.date` | TR biçim `d.m.Y` |
| `x-field.textarea`, `x-field.toggle`, `x-field.file` | |
| `x-modal` | başlık, gövde, alt butonlar, Esc ile kapanır |
| `x-confirm` | yıkıcı işlemler için onay |
| `x-toast` | başarı/hata bildirimi |
| `x-page-header` | başlık, breadcrumb, eylem butonları |
| `x-tabs` | detay ekranı sekmeleri |

## Lookup bileşeni — özel dikkat

Cari ve ürün seçimi sistemin en çok kullanılan alanı. Davranış:

- Kod yazılıp Enter'a basılınca birebir eşleşme doğrudan seçilir
  (**barkod okuyucu bu şekilde çalışır**)
- Birden çok eşleşme varsa açılır liste
- "Detaylı ara" düğmesi tam ekran arama penceresi açar: çok alanlı arama,
  daraltma kutuları, klavye ile gezinme (↑↓ Enter Esc)
- Seçim sonrası odak bir sonraki alana geçer

## Tutar ve miktar biçimi
- Ekranda `1.234,56`, veritabanında nokta
- Miktar 3 hane, tutar 2 hane gösterilir (saklama 4 hane)
- Sağa hizalı, tabular rakam


## Kurallar

**Faz bağlamı:** Faz 0b — UI bileşenleri; business rule UI içine gömülmez.

### Göreve özel kararlar
- Form iş kuralı taşımaz; Action'a delege eder.
- Yeni kayıt formu boş/kendi defaultlarıyla açılır.
- Period lookup aktif PeriodContext ister.


### Uygulama ayrıntıları
- Form input, modal, doğrulama mesajı ve bildirim bileşenleri business rule içermez.
- Yeni kayıt formu başka bir kayıt/örnek veri ile dolu açılmaz; yalnız tanımlı default değerler kullanılır.
- Lookup alanı period karta bakıyorsa aktif PeriodContext gerektirir.
- Kaydetme eylemi Action sınıfına delege edilir; Livewire yalnız input/interaction katmanıdır.

## Kabul ölçütü
- Barkod okuyucuyla kod okutunca satır ekleniyor, odak kaybolmuyor
- Modal Esc ile kapanıyor, arka plan kaymıyor
- Hatalı form alanı kırmızı çerçeve ve mesaj gösteriyor


## İstem
> Yukarıdaki blade bileşenlerini yaz. Lookup bileşeni barkod okuyucuyla
> çalışacak şekilde: Enter'da birebir eşleşme doğrudan seçilsin, odak
> korunsun. Tutar ve miktar TR biçiminde gösterilsin. Tema sınıflarını
> kullan, yeni CSS dosyası açma.
