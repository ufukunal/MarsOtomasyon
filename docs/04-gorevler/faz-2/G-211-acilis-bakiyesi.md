# G-211 — Açılış bakiyesi

## Amaç
Canlıya geçişte mevcut stoğun sisteme alınması. Faz 11'in provası.

## Önkoşul
G-202, G-112 (içe aktarma)

## Akış

1. Excel/JSON dosyası: ürün kodu, lokasyon kodu, miktar, birim maliyet
2. Önizleme ve doğrulama (ürün var mı, lokasyon var mı, miktar pozitif mi)
3. Onay → her satır için `RecordStockMovement`:
   - `direction = in`
   - `reason = opening`
   - `movement_date` = açılış tarihi
   - `updatesAverage = true` (ilk maliyet buradan gelir)
4. Sonuç raporu

## Kurallar
- Açılış **bir kez** yapılır; ikinci kez denenirse uyarı verir
- Açılış tarihi dönem açık olmalı
- Birim maliyet zorunlu — maliyetsiz açılış stok değerini bozar
- Açılış hareketleri `reason = opening` ile işaretli, raporlarda ayrılabilir
- Tamamı tek transaction; bir satır hatalıysa hiçbiri uygulanmaz

## Kabul ölçütü
- 1000 satırlık açılış hatasız uygulanıyor
- Ortalama maliyet açılış fiyatından oluşuyor
- İkinci açılış denemesi uyarı veriyor
- Hatalı satırda tamamı geri alınıyor

## İstem
> Açılış bakiyesi içe aktarma ekranını ve action'ını yaz. Her satır için
> RecordStockMovement çağrılsın, reason = opening olsun. Tamamı tek
> transaction içinde uygulansın. Birim maliyet zorunlu olsun.
