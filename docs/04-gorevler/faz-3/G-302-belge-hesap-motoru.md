# G-302 — Belge hesap motoru

## Amaç

Satır iskonto, belge iskonto, KDV gruplama ve yuvarlama farkını tek deterministik hesap motorunda toplamak.

## Önkoşul

G-301, K-008, K-035, K-036, K-037, K-080.

## Dokunulacak dosyalar

- `app/Actions/Documents/CalculateDocumentTotals.php`
- `app/DataObjects/Documents/DocumentLineCalculation.php`
- `app/DataObjects/Documents/DocumentTotals.php`
- `app/Support/Money.php` / mevcut Money altyapısı
- `tests/Unit/Documents/CalculateDocumentTotalsTest.php`

## Şema / Kod

Action dışarıdan decimal string alır ve decimal string döndürür.

```php
final class CalculateDocumentTotals
{
    public function handle(array $lines, string $discountRate, string $discountAmount): DocumentTotals
    {
        // float YOK
    }
}
```

Her satır için:

```
gross = quantity × unit_price
line_discount = yüzde/tutarın normalize edilmiş değeri
line_total = gross - line_discount
```

Belge için:

```
subtotal = Σ line_total
discount = yüzde/tutar
tax_base = subtotal - discount
```

KDV:

1. Satırları `vat_rate` grubuna ayır.
2. Belge iskontosunu grup matrahlarına oranlı dağıt.
3. Dağıtımda yüksek BCMath hassasiyeti kullan; satır başına 2 hane yuvarlama yapma.
4. Her KDV grubunu bir kez hesapla.
5. Grup KDV'sini 2 haneye half-up yuvarla.
6. `vat_amount` grup toplamı.
7. `grand_total` 2 hane half-up.
8. `rounding_difference` sakla.

## İskonto input kuralı

UI kullanıcıya yüzde veya tutar girdirir.

- Yüzde değiştiğinde tutar hesaplanır.
- Tutar değiştiğinde yüzde hesaplanır.
- Action'a iki değer birlikte geliyorsa matematiksel olarak uyumlu olmalı.
- Çelişkili rate/amount sessizce birini seçmez; validation/DomainException verir.

## KDV toplu eylemleri

Ayrı UI helper/action:

- `ApplyProductVatToLines`
- `ClearVatFromLines`

yalnız draft/düzenlenebilir belgede çalışır.

## Fiyat uyarıları

Bu görev fiyatı çözmez; fakat satır hesap API'si G-110/G-26 fiyat çözümünden gelen değeri kullanır.

%20+ sapma:
- hesap sonucunu değiştirmez,
- bloklamaz,
- G-304/G-305/G-307 UI akışında uyarı+audit üretir.


## Kurallar

- Tüm decimal aritmetik string + BCMath/Money.
- İskonto KDV'den önce.
- KDV oran grubu bazında bir kez hesaplanır.
- Ara adımda 2 hane yuvarlama yok; rounding_difference saklanır.

## Kabul ölçütü

- 100 × 33.33 örneğinde kuruş sapması yok.
- Birden fazla KDV oranı grup bazında doğru.
- Satır satır KDV yuvarlama yapılmıyor.
- Satır iskonto + belge iskonto sırası doğru.
- İskonto KDV'den önce.
- Yüzde/tutar eşdeğer girişleri aynı sonucu veriyor.
- Çelişkili yüzde/tutar reddediliyor.
- rounding_difference grand_total CHECK ile uyumlu.
- Kodda PHP float yok.
- Test beklenenleri production fonksiyonuyla hesaplamıyor; elle sabit değer kullanıyor.

## İstem

> CalculateDocumentTotals ve DTO'larını iş kuralı 28'e göre yaz. BCMath/string kullan; ara adımda 2 hane yuvarlama yapma. KDV'yi oran grubu bazında bir kez hesapla. İskonto KDV'den önce olsun ve rounding_difference saklansın.
