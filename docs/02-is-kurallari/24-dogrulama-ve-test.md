# Doğrulama ve test standardı

## Girdi doğrulama — nerede yapılır

Doğrulama **iki yerde** yapılır ve ikisi farklı işler:

| Katman | Sorumluluk | Örnek |
|---|---|---|
| **Form / Livewire** | Biçim ve zorunluluk | "Unvan boş olamaz", "E-posta geçersiz" |
| **Action** | İş kuralı | "Kapalı döneme kayıt girilemez", "Stok yetersiz" |

**İş kuralı forma yazılmaz.** Aynı kural içe aktarmadan, kuyruktan ve
testten de geçmek zorunda; forma yazılan kural bunları atlar.

## Standart kurallar

```php
// tutar
'unit_price' => ['required','decimal:0,4','min:0'],
// miktar
'quantity'   => ['required','decimal:0,3','gt:0'],
// oran
'vat_rate'   => ['required','numeric','between:0,100'],
// kod
'code'       => ['required','string','max:40','regex:/^[A-Z0-9._-]+$/'],
// cari — AKTİF ŞİRKETE AİT OLMALI
'contact_id' => ['required', Rule::exists('master.contacts','id')
                    ->where('company_id', PeriodContext::companyId())],
```

**Son satır kritik:** `exists` kuralı şirket filtresi olmadan yazılırsa,
kullanıcı başka şirketin cari id'sini gönderip belgeye bağlayabilir.
Global scope `exists` kuralında çalışmaz.

## Türkçe hata mesajları

`lang/tr/validation.php` çevrilir. Alan adları `attributes` bölümünde
Türkçe tanımlanır ki "The contact_id field is required" yerine
"Cari alanı zorunludur" çıksın.

## Test standardı

### Kapsam hedefi

| Katman | Hedef |
|---|---|
| Action (iş kuralı) | **%90+** — zorunlu |
| Model (hesaplanan alan) | %80+ |
| Livewire bileşeni | kritik akışlar |
| Blade | yok |

Kapsam sayısı amaç değil; **hesap yapan her fonksiyonun testi olmalı**.

### Her Action için asgari üç test

1. **Mutlu yol** — doğru girdi, beklenen sonuç, **veritabanından geri okunarak**
2. **Kural ihlali** — istisna fırlatıyor mu
3. **İzolasyon** — başka şirketin verisine dokunmuyor mu

### Hesap testlerinde beklenen değer elle hesaplanır

```php
// 10 adet x 100 TL, %20 KDV, %10 iskonto
// ara toplam 1000, iskonto 100, matrah 900, KDV 180, toplam 1080
expect($doc->grand_total)->toBe('1080.0000');
```

Beklenen değeri koddan üretme — o zaman test kodu doğrulamaz, tekrarlar.

### Zorunlu testler

Aşağıdakiler her fazda yazılır, atlanamaz:

- **İzolasyon**: A şirketinin verisi B'de görünmüyor
- **Eşzamanlılık**: paralel işlemde bakiye tutarlı
- **Çift gönderim**: aynı anahtarla iki istek → tek kayıt
- **Yetki**: izinsiz kullanıcı 403 alıyor, maliyet kolonu üretilmiyor
- **Dönem**: kapalı aya kayıt engelleniyor
- **Para**: kuruş farkı oluşmuyor

### Test veritabanı

Testler **gerçek PostgreSQL'e** karşı çalışır, SQLite'a değil.
CHECK kısıtları, `lockForUpdate` ve trigram indeksi SQLite'ta yok;
SQLite ile geçen test canlıda patlar.

`RefreshDatabase` kullanılır. Dönem testleri için sahte bir dönem
veritabanı (`test_period`) oluşturulur.

## Kod kalitesi

```bash
./vendor/bin/pint            # biçim
./vendor/bin/phpstan analyse # seviye 6
./vendor/bin/pest            # testler
composer audit               # bilinen açık
```

Dördü de geçmeden dağıtım yapılmaz.
