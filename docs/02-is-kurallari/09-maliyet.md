# Maliyet hesabı

## Yöntem: hareketli ortalama

Tek yöntem hareketli ortalamadır (K-006). FIFO/parti maliyeti yoktur; lot/parti takibi kapsam dışıdır (K-005).

## Para disiplini

Miktar ve maliyet hesaplarında PHP `float` kullanılmaz. Veritabanı decimal değerleri string olarak okunur; BCMath/Money tabanlı hesap yapılır.

## Giriş formülü

Maliyete giren stok girişinde:

```
mevcut_deger = mevcut_miktar × mevcut_ortalama
giren_deger  = giren_miktar × giren_birim_maliyet
yeni_ortalama = (mevcut_deger + giren_deger) ÷ (mevcut_miktar + giren_miktar)
```

Tüm işlemler yeterli ara scale ile BCMath üzerinden yürür. `product_costs.moving_average` 4 hanelik decimal snapshot olarak normalize edilir.

## UpdateMovingAverage sözleşmesi

`UpdateMovingAverage` yalnız gerçek maliyet etkili stok girişinin transaction'ı içinde çağrılır.

Girdiler:

- product_id
- incoming_base_quantity string
- incoming_unit_cost_try string
- document_date
- source document bilgisi

Davranış:

1. ilgili `product_costs` satırı `lockForUpdate` ile alınır,
2. ürünün toplam mevcut stok miktarı tutarlı kilit sırasıyla okunur,
3. stok sıfır veya negatifse yeni moving average doğrudan giren birim maliyet olur,
4. aksi halde hareketli ortalama formülü BCMath ile hesaplanır,
5. `moving_average`, `last_purchase_price`, `last_purchase_at` aynı transaction içinde güncellenir.

## Hangi hareketler maliyeti değiştirir

`product_costs` için maliyet etkili girişler:

- purchase
- production
- opening

Çıkış hareketi ortalamayı değiştirmez. Çıkışın unit_cost değeri o anki moving average snapshot'ıdır.

Transfer ortalamayı değiştirmez; çıkış ve giriş aynı birim maliyetle yazılır.

Faz 7 ithalatında purchase invoice ilk maliyeti normal alış gibi moving average'a sokar. Sonradan dağıtılan ithalat ek maliyeti fiziksel stock movement değildir; K-123 gereği `inventory_cost_adjustments` gerçek kaynağı üzerinden maliyet düzeltmesidir. Bu düzeltmenin satış görmüş / sıfır-negatif mevcut stokta moving_average'a uygulanma formülü A-053 kararı bekler.

Faz 6 satış iadesi karantinaya girer ve moving average'ı yeniden hesaplamaz:
- kaynaklı satış iadesi original sales stock-out unit_cost ile geri girer,
- kaynaksız satış iadesi current moving average unit_cost kullanır.

Purchase return bir stok çıkışıdır ve moving average'ı değiştirmez. Kaynaklı purchase return'de K-104 gereği kullanıcı current moving average veya source purchase cost temelini seçer; kaynaksızda yalnız current moving average kullanılır.

## Alış faturası maliyeti

K-087 gereği Faz 4'te stok ve maliyet etkisi mal kabulde değil, alış faturası posting anında oluşur.

Alış satırı için stok birim maliyeti:

```
satir_net = satır brüt - satır iskontosu - satıra dağıtılmış belge iskontosu
net_try   = satir_net × frozen exchange_rate
unit_cost_try = net_try ÷ base_quantity
```

- KDV stok maliyetine eklenmez.
- `exchange_rate` belge üzerinde dondurulmuş snapshot'tır.
- `unit_cost_try` temel stok birimi başınadır.
- Aynı alış faturası satırı ikinci kez maliyet güncellemesi üretemez; posting idempotent'tır.

## Stok sıfır veya negatifken giriş

Mevcut toplam miktar `<= 0` ise hareketli ortalama doğrudan giren birim maliyete eşitlenir. Negatif geçmiş miktarla ağırlıklı ortalama türetilmez.

## Alış fiyatı sapma uyarısı

K-007 gereği alış girişinde birim maliyet mevcut hareketli ortalamadan mutlak **%25 veya üzeri** sapıyorsa:

- kullanıcıya açık uyarı gösterilir,
- kullanıcı devam edebilir,
- posting engellenmez,
- period `activity_log` içine ürün, referans maliyet, girilen maliyet, sapma yüzdesi ve actor yazılır.

Eşik Master `companies.cost_deviation_threshold` alanından okunur; varsayılan 25'tir. Kaynak kararlarda ürün grubu bazlı override yoktur.

Mevcut hareketli ortalama 0 ise yüzde sapma hesaplanmaz; ilk alış fiyatı doğrudan başlangıç maliyetidir.

## Eşzamanlılık

Aynı ürüne eşzamanlı iki maliyet etkili giriş:

- aynı ürün maliyet satırını deterministik sırayla kilitler,
- her ikinci işlem birincinin commit edilmiş yeni miktar/maliyet durumunu görerek hesap yapar,
- idempotency aynı belgeyi ikinci kez maliyete sokamaz.

## Bütünlük

`integrity:stock` / maliyet kontrolü en az:

- maliyet etkili girişlerin unit_cost ve total_cost tutarlılığını,
- `product_costs.last_purchase_price` snapshot'ını,
- son maliyet etkili alış sonrası `last_purchase_at` değerini,
- mümkün olduğu noktada moving average zincirini

kontrol eder.

Fark raporlanır; otomatik düzeltme yapılmaz.
