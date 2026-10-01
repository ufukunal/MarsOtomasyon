# Güvenlik ve kişisel veri

## Kimlik doğrulama

- Parola en az 10 karakter, harf + rakam zorunlu
- Parola `bcrypt` ile saklanır (Laravel varsayılanı)
- 5 hatalı denemeden sonra **15 dakika kilit** (IP + e-posta bazlı)
- Oturum 8 saat hareketsizlikte düşer
- Parola değişince diğer oturumlar sonlanır
- Yönetici hesapları için **iki adımlı doğrulama** önerilir (Faz 11)

## Yetki — hatırlatma

Yetki kontrolü **beş noktada** yapılır: menü, rota, bileşen, action,
policy. Yalnız menüde gizlemek yetmez; kullanıcı isteği elle gönderebilir.

`cost.view` izni yoksa maliyet **üretilmez**, gizlenmez.

## Dosya yükleme

Saldırı yüzeyinin en geniş olduğu yer burasıdır.

| Kontrol | Kural |
|---|---|
| Uzantı | Beyaz liste: jpg, jpeg, png, webp, pdf, xlsx, csv, docx |
| MIME | Gerçek içerikten doğrulanır (`finfo`), uzantıya güvenilmez |
| Boyut | Varsayılan 25 MB |
| Dosya adı | Yeniden üretilir (UUID); kullanıcının adı yalnız `original_name`'de saklanır |
| Konum | `storage/app/attachments` — **web kökü dışında** |
| Erişim | Doğrudan URL yok; yetki kontrolünden geçen bir rota üzerinden servis edilir |
| SVG | **Yasak** — içinde script taşır |
| Çift uzantı | `fatura.pdf.php` reddedilir |

```php
$path = $file->storeAs(
    'attachments/'.date('Y/m'),
    Str::uuid().'.'.$file->getClientOriginalExtension(),
    'attachments'
);
```

## İstek sınırlama

| Uç nokta | Sınır |
|---|---|
| Giriş | 5 / dakika / IP |
| Parola sıfırlama | 3 / saat / e-posta |
| Dosya yükleme | 30 / dakika / kullanıcı |
| Rapor / dışa aktarma | 10 / dakika / kullanıcı |
| Pazaryeri webhook | 120 / dakika / kanal |

## Kişisel veri (KVKK)

Sistemde kişisel veri var: cari yetkilileri (ad, telefon, e-posta),
gerçek kişi cariler (TC kimlik no), kullanıcılar.

**Gereken düzenlemeler:**

- **TC kimlik numarası** maskelenerek gösterilir (`123*****89`), tam hali
  yalnızca `contacts.view_sensitive` izni olanda görünür
- Kişisel veri içeren dışa aktarmalar `activity_log`'a düşer — kim,
  ne zaman, kaç kayıt aldı
- Cari kartında **"kişisel verileri sil"** eylemi: ad ve iletişim
  bilgileri anonimleştirilir, belgeler ve bakiye korunur
  (ticari kayıt saklama yükümlülüğü devam eder)
- Log dosyalarına TC kimlik numarası ve tam telefon yazılmaz
- Yedekler şifrelenir (`spatie/laravel-backup` parola desteği)

> **[VARSAYIM]** KVKK tarafında aydınlatma metni, veri envanteri ve
> saklama süreleri hukuki konudur; burada yalnız teknik önlemler
> tanımlanmıştır. **Kullanıcının hukuki danışmanına doğrulatması gerekir.**

## Yedek güvenliği

- Yedekler şifrelenir
- Harici hedefe yazılır (aynı sunucu değil)
- Geri yükleme provası **aylık**, sonucu kaydedilir
- Yedek dosyalarına web üzerinden erişilemez

## Güncellik

`composer audit` dağıtım öncesi çalıştırılır. Bilinen açığı olan paketle
canlıya çıkılmaz.
