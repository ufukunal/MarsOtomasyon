# Belge hesaplama

Faz 3 **satırlı ticari belgelerinde** hesaplama tek motor üzerinden yapılır. Aynı hesap motoru ileriki fazlarda alış belgeleri tarafından da kullanılabilir.

`collection` ve `contact_debit_credit` satırlı belge değildir; G-302 motoruna girmez. Bu tipler header-amount sözleşmesi kullanır: `subtotal = tax_base = grand_total = amount`, `discount_amount = vat_amount = rounding_difference = 0`.

## Girdi

Her satır:

- quantity — decimal(18,3)
- unit_price — decimal(18,4), KDV hariç
- line_discount_rate — decimal(7,4)
- line_discount_amount — decimal(18,4)
- vat_rate — decimal(7,4)

Belge:

- discount_rate
- discount_amount

Tüm aritmetik string + BCMath / Money ile yapılır. PHP float yasaktır.

## Hesap sırası

```
satır brüt      = quantity × unit_price
satır iskonto   = girilen yüzde/tutarın hesaplanan karşılığı
satır toplamı   = satır brüt - satır iskonto              (4 hanelik para snapshot; 2 hane half-up YOK)

subtotal        = Σ line_total                             (4 hanelik para snapshot)
belge iskonto   = subtotal üzerinden                      (4 hanelik para snapshot)
tax_base        = subtotal - belge iskonto                (4 hanelik para snapshot)

KDV:
  aynı vat_rate satırlarının belge iskonto payı sonrası matrahı toplanır
  her oran grubu için KDV BİR KEZ hesaplanır
  grup KDV'si 2 haneye half-up yuvarlanır

vat_amount      = grup KDV toplamı
grand_total     = tax_base + vat_amount                    (2 hane half-up)
rounding_difference saklanır
```

## İskonto

K-080:

- Satırda yüzde veya tutar girilebilir.
- Belgede yüzde veya tutar girilebilir.
- Kullanıcı hangisini değiştirirse diğeri Money/BCMath ile hesaplanır.
- Kesinleşmede ikisi de snapshot olarak saklanır.
- İskonto KDV'den önce uygulanır.

Belge iskontosunun satırlara/KDV gruplarına dağıtımı oranlı yapılır. Ara hesaplarda 2 hanelik half-up yapılmaz; BCMath yeterli yüksek scale ile çalışır. DB'deki para snapshot alanları 4 hane olarak normalize edilir. KDV yalnız oran grubu toplamında, grand total ise belge sonunda 2 hane half-up yuvarlanır.

## KDV toplu eylemleri

Sipariş ve fatura ekranında:

- **Tümüne KDV uygula**: her ürün satırını ürün kartındaki satış KDV oranına getirir.
- **KDV temizle**: seçili/tüm satır vat_rate değerini 0 yapar.

Bu eylemler yalnız taslak/düzenlenebilir belgede çalışır.

## Fiyat çözümleme

Satır eklenirken başlangıç birim fiyatı:

1. cari fiyat listesi
2. varsayılan fiyat listesi
3. products.list_price
4. 0

Konfigüratör fiyatı değiştirmez.

Kullanıcı fiyatı değiştirebilir. Çözümlenen referans fiyatın mutlak %20 veya üzeri sapmasında uyarı + period audit yazılır; blok yoktur.

Maliyet altı satışta uyarı vardır. `cost.view` yoksa maliyet tutarı kullanıcı payload'ında hiç üretilmez.

## Bütünlük

`integrity:documents` document type'a göre çalışır.

Line-calculated tiplerde:

- line_total'ları yeniden hesaplar,
- subtotal/discount/tax_base değerini karşılaştırır,
- KDV oran gruplarını yeniden hesaplar,
- rounding_difference dahil grand_total kontrolünü yapar,
- farkı raporlar, otomatik düzeltmez.

Header-amount tiplerde (`collection`, `contact_debit_credit`):

- document_lines beklemez,
- `subtotal = tax_base = grand_total = amount` header invariant'ını,
- discount/vat/rounding değerlerinin 0 olduğunu,
- document_id bağlı contact transaction tutarını ve collection için cash/bank movement tutarını karşılaştırır.
