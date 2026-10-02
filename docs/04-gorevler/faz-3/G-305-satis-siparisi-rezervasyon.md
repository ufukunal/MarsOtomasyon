# G-305 — Satış siparişi ve çok lokasyonlu rezervasyon

## Amaç

Satış siparişini onaylamak, risk uyarısını göstermek ve seçili satırları mevcut kullanılabilir stok kadar bir veya daha fazla lokasyona rezerve etmek.

## Önkoşul

G-301, G-302, G-210, G-304.

## Dokunulacak dosyalar

- `app/Actions/Sales/ConfirmSalesOrder.php`
- `app/Actions/Sales/ReserveSalesOrderLines.php`
- `app/Actions/Sales/CancelSalesOrderRemaining.php`
- `app/Actions/Sales/CalculateSalesRiskProjection.php`
- `app/Livewire/Sales/SalesOrderList.php`
- `app/Livewire/Sales/SalesOrderEditor.php`
- `tests/Feature/Sales/SalesOrderTest.php`


## Şema / Kod

Yeni tablo yok. `documents`, `document_lines` ve Faz 2 `stock_reservations` kullanılır. Sipariş satırı fulfillment miktarları child `source_line_id` toplamından hesaplanır.

## Ekran

v65 tabs:

- Hareketler
- Bilgiler
- Risk
- Rezervasyon
- Sevkiyat
- Faturalama
- Requirement Snapshot
- Dosyalar
- Notlar
- Timeline

Eylemler:

- Kaydet
- Onayla
- Hold
- Rezervasyon Yap
- Sevkiyat Oluştur
- Fatura Oluştur
- Kalanı İptal
- Kapat

## Onay

`ConfirmSalesOrder`:

1. idempotency doğrular,
2. `EnsurePeriodOpen(document_date)`,
3. G-302 totals tekrar hesaplar,
4. risk projeksiyonunu oluşturur,
5. gerekiyorsa uyarı payload'ı üretir,
6. kullanıcı uyarıyı kabul etmişse numara üretir,
7. status = `confirmed`,
8. actor/audit yazar.

Sipariş onayı stok veya cari hareket oluşturmaz.

## Risk

```
exposure =
  cari bakiye
+ bu sipariş grand_total
+ henüz tahsil edilmemiş portföy çek/senet riski
```

Diğer açık siparişler resmî cari bakiyeye eklenmez.

`exposure > contacts.risk_limit` ise:

- açık uyarı,
- limit aşım tutarı göster,
- bloklama yok.

Faz 5 çek/senet modülü henüz yoksa yeni fake tablo kurulmaz ve portföy kıymet riski **0 diye bilinmiş değer gibi kabul edilmez**.

- Kaynak mevcutsa tam projeksiyon = cari bakiye + bu sipariş + portföy kıymet riski.
- Kaynak henüz yoksa `security_risk = unknown` / `projection_complete = false` olarak DTO/UI seviyesinde işaretlenir; DB'ye yeni alan yazılmaz.
- Bilinen kısım `cari bakiye + bu sipariş` tek başına risk limitini aşıyorsa normal limit aşım uyarısı verilir.
- Bilinen kısım limiti aşmıyorsa kullanıcıya "Çek/senet riski henüz Faz 5 kaynağı olmadığı için tam projeksiyona dahil edilemiyor" bilgisi gösterilir; eksik değer sessizce 0 gösterilmez.
- Faz 5 securities kaynağı geldiğinde aynı hesap servisi K-078 tam formülünü kullanır.

## Rezervasyon

Kullanıcı satır bazında rezerv seçer.

`ReserveSalesOrderLines`:

1. order confirmed olmalı,
2. cancelled/fulfilled miktarı çıkar,
3. kullanılabilir miktarı lokasyonlar arasında tarar,
4. deterministik lokasyon sırasıyla mevcut kadar `ReserveStock`,
5. kalan miktarı açık bırakır.

Bir satır birden fazla `stock_reservations` kaydı üretebilir.

Negatif stok izni negatif rezervasyon üretmez.

## Kalanı iptal

`CancelSalesOrderRemaining`:

- kaynak quantity'yi değiştirmez,
- yalnız gerçek kalan kadar `cancelled_quantity` artırır,
- iptal edilen kısma ait aktif rezervleri çözer,
- iptal miktarı sonradan rezerv/sevk/fatura edilemez,
- kullanılabilir kalan 0 ise sipariş `closed`.


## Kurallar

- Sipariş onayı stok/cari hareket üretmez.
- Rezervasyon yalnız kullanılabilir stok kadar oluşur.
- Negatif stok izni negatif rezervasyon değildir.
- Risk aşımı uyarıdır, blok değildir.

## Kabul ölçütü

- Confirm numarayı bir kez üretir.
- Risk aşımı uyarı veriyor ama onayı engellemiyor.
- Çek/senet kaynağı yokken security risk sessizce 0 gösterilmiyor; projeksiyon eksik olarak işaretleniyor. Bilinen bakiye+sipariş tek başına limiti aşıyorsa uyarı yine çıkıyor.
- Rezervasyon fiziksel quantity'yi değiştirmiyor.
- 10 adet ihtiyaç, iki depoda 6+4 stok ise iki reservation oluşturabiliyor.
- Toplam kullanılabilir 7 ise yalnız 7 rezerve, 3 açık kalıyor.
- cancelled_quantity sonrası aynı miktar yeniden kullanılamıyor.
- Kalanı iptal aktif rezervi doğru çözüyor.
- Concurrency'de iki rezerv aynı kullanılabilir stoğu aşmıyor.

## İstem

> Satış siparişi ekranı ve Confirm/Reserve/CancelRemaining action'larını bu göreve göre uygula. Rezervasyon satır bazında ve çok lokasyonlu olsun; negatif rezervasyona izin verme. Risk uyarı olsun, blok olmasın. Sipariş onayında stok/cari hareket yazma.
