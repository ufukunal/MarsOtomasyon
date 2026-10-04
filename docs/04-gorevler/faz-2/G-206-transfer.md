# G-206 — Depo transferi

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, `company_id` kolonu
yoktur, migration `database/migrations/period/` altına yazılır.


## Amaç
Lokasyonlar arası mal aktarımı. Araca yükleme (sıcak satış) de budur.

## Önkoşul
G-202


## Dokunulacak dosyalar
- transfers/transfer_lines period migration'ları
- Transfer/TransferLine period modelleri
- transfer list/detail Livewire + blade
- SendTransfer/ReceiveTransfer/CancelTransfer Action'ları
- `tests/Feature/Stock/TransferFlowTest.php`

## Şema / Kod
```php
// transfers: id, number, from_location_id, to_location_id,
//   transfer_date, status(draft|in_transit|partially_received|received|cancelled),
//   note, created_by, posted_by, posted_at
// transfer_lines: id, transfer_id, product_id,
//   quantity decimal(18,3), received_quantity decimal(18,3) default 0
```

## Akış

```
1. Transfer açılır, ürün ve miktar girilir → draft
2. "Gönder"
   → çıkış lokasyonundan ÇIKIŞ hareketi (reason = transfer)
   → status = in_transit, numara verilir
   → mal "yolda": çıkıştan düşmüş, girişe girmemiş
3. "Teslim Al" (kısmi olabilir)
   → giriş lokasyonuna GİRİŞ hareketi, AYNI birim maliyetle
   → tamamı alındıysa received, değilse partially_received
```

## Maliyet kuralı

**Transfer maliyeti değiştirmez.** Çıkış hareketinin birim maliyeti
(o anki ortalama) giriş hareketine de aynen yazılır ve
`updatesAverage = false` geçilir.

Aksi halde mal kendi deposu arasında gezdikçe maliyeti değişirdi.

## Kurallar
- Kaynak ve hedef lokasyon aynı olamaz
- Kısmi teslim mümkün; kalan "yolda" kalır
- Yolda olan transfer iptal edilirse çıkış hareketi ters kayıtla geri alınır
- Karantinadaki mal transfer edilemez

## Ekran
Liste: numara, kaynak, hedef, tarih, durum, satır sayısı.
Detay: satırlar, gönderilen ve teslim alınan miktar, kalan.


### Göreve özel kararlar
- Transferde çıkış kaynakta, giriş teslimde hedefte; yolda miktar ayrıca türetilir.
- Kısmi teslim desteklenir; maliyet transferde değişmez.


### Uygulama ayrıntıları
- Transfer kaynak lokasyondan çıkış ve hedef lokasyona giriş olarak izlenir.
- Yoldaki transfer hedef kullanılabilir stokta görünmez; teslimle hedef giriş oluşur.
- Kısmi teslim destekleniyorsa teslim edilen miktar kadar giriş yazılır, kalan transit kalır.
- Transfer maliyeti kaynak hareketli ortalama maliyetini taşır; transfer kendi başına yeni ortalama fiyat yaratmaz.

## Kabul ölçütü
- Gönderince kaynak azalıyor, hedef değişmiyor
- Teslim alınca hedef artıyor
- Ortalama maliyet hiç değişmiyor
- Kısmi teslim kalanı doğru takip ediyor
- Aynı lokasyona transfer reddediliyor


## İstem
> transfers ve transfer_lines tabloları için migration, modeller, ekranlar,
> gönderme ve teslim alma action'larını yaz. Giriş hareketi çıkışın birim
> maliyetiyle yazılsın ve ortalamayı güncellemesin.
