# Yazdırma soyutlaması

## Kural

Uygulama **asla** doğrudan yazıcıya konuşmaz. Tek giriş noktası:

```php
PrintManager::send(PrintType::A4, $payload);
```

Taşıyıcı değişince yalnızca bu sınıfın arkasındaki sürücü değişir.

## Sürücüler

| Sürücü | Durum | Nasıl çalışır |
|---|---|---|
| `BrowserDriver` | **Faz 0'da bu** | PDF üretir, tarayıcının yazdırma penceresine verir |
| `AgentDriver` | ileride | Yerel ajana HTTP ile ham komut gönderir (ESC/POS, ZPL) |
| `ShellDriver` | ileride | Özel tarayıcı kabuğuna köprü |

Sürücü `config('printing.driver')` ile seçilir.

## Profil çözümleme

Yazdırma anında `print_profiles` şu sırayla aranır:
şirket+kullanıcı+makine+tip → şirket+kullanıcı+tip → şirket+tip → sistem varsayılanı.

Profil, ilk aşamada **şablon ve kağıt boyutunu** belirler; ileride gerçek
cihaz adını da taşıyacaktır.

## Neden şimdi kuruluyor

Sonradan eklemek, her yazdırma noktasına tek tek dokunmak demektir.
Şimdi tek arayüz arkasına alınırsa taşıyıcı değişimi tek dosyalık iştir.
