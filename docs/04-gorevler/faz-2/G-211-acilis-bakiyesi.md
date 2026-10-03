# G-211 — Açılış bakiyesi

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, `company_id` kolonu
yoktur, migration `database/migrations/period/` altına yazılır.


## Amaç
Canlıya geçişte mevcut stoğun sisteme alınması. Faz 11'in provası.

## Önkoşul
G-202, G-112 (içe aktarma)


## Dokunulacak dosyalar
- opening balance import Livewire + blade
- ImportOpeningStock Action/job
- opening row validator/DTO
- import result report
- `tests/Feature/Stock/OpeningBalanceTest.php`


## Şema / Kod

Mevcut şema/kod örnekleri aşağıdaki kanonik stok sözleşmesiyle birlikte uygulanır; çelişkide kanonik sözleşme üstündür.

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


### Göreve özel kararlar
- Açılış hareketi reason=opening; period devrinde closing moving average unit_cost olarak taşınır.
- Aynı şirket devirde stock_balance ID korunur; geçmiş hareket ID'leri taşınmaz.


### Uygulama ayrıntıları
- Açılış stok hareketi `reason=opening` ile yazılır.
- Dönem devrinde yeni yıl unit_cost değeri kaynak yıl kapanış hareketli ortalamasıdır.
- Aynı şirket dönem devrinde taşınan stock_balance ID'leri korunur; geçmiş stock_movements taşınmaz.
- Manuel başlangıç açılışı ile otomatik dönem devri açılışı kaynak/audit bilgisiyle ayırt edilir.

## Kabul ölçütü
- 1000 satırlık açılış hatasız uygulanıyor
- Ortalama maliyet açılış fiyatından oluşuyor
- İkinci açılış denemesi uyarı veriyor
- Hatalı satırda tamamı geri alınıyor


## İstem
> Açılış bakiyesi içe aktarma ekranını ve action'ını yaz. Her satır için
> RecordStockMovement çağrılsın, reason = opening olsun. Tamamı tek
> transaction içinde uygulansın. Birim maliyet zorunlu olsun.
