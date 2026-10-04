# Fiyatlandırma

## Satış fiyatı çözümleme

1. Cari karta atanmış fiyat listesi
2. Varsayılan fiyat listesi
3. products.list_price
4. Bulunamazsa 0 ve kullanıcı girişi

Konfigüratör fiyatı etkilemez.

## Fiyat değişikliği

Kullanıcı satır fiyatını değiştirebilir. Çözümlenen liste fiyatından mutlak sapma **%20 veya daha fazlaysa**:

- açık uyarı gösterilir,
- activity_log'a eski/yeni fiyat, yüzde sapma ve actor yazılır,
- işlem **engellenmez**,
- `prices.override` zorunlu değildir.

Maliyet altı satış uyarısı ayrıca devam eder. `cost.view` yoksa maliyet tutarı hiçbir payload/HTML/export içinde üretilmez.

## KDV / iskonto

Birim fiyat DB'de KDV hariç saklanır. Satır ve belge iskontosu yüzde veya tutar girilebilir; biri değişince diğeri Money/BCMath ile hesaplanır. Kesinleşmede oran+tutar dondurulur. İskonto KDV'den önce matrahı düşürür.
