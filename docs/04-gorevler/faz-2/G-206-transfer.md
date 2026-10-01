# G-206 — Depo transferi

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, `company_id` kolonu
yoktur, migration `database/migrations/period/` altına yazılır.


## Amaç
Lokasyonlar arası mal aktarımı. Araca yükleme (sıcak satış) de budur.

## Önkoşul
G-202


## Dokunulacak dosyalar
- `database/migrations/period/`

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


## Kurallar

### Göreve özel kararlar
- Transferde çıkış kaynakta, giriş teslimde hedefte; yolda miktar ayrıca türetilir.
- Kısmi teslim desteklenir; maliyet transferde değişmez.

### Kanonik Faz 2 stok sözleşmesi
- Faz 2 tabloları PERIOD DB'dedir; company_id yoktur.
- PeriodModel kullanılır; BelongsToCompany/global scope yoktur.
- Ürün/lokasyon/birim kartları aynı period DB'dedir ve gerçek FK kullanılır.
- Master user actor alanlarında gerçek FK kurulmaz; user_id + user_name snapshot tutulur.
- Stok hareketinin tek yazma kapısı `RecordStockMovement` Action'ıdır.
- `stock_balances` doğrudan business kodundan yazılmaz.
- Stok hareketi daima ürünün temel birimindedir.
- Belge/kaynak miktarı farklı birimdeyse frozen conversion_factor + base_quantity hesaplanır.
- Dönüşüm eksikse factor=1 varsayılmaz; işlem engellenir.
- stock_movements.quantity daima pozitif; yön `direction` alanındadır.
- Hareket silinmez/değiştirilmez; ters hareket yazılır.
- Moving average maliyet tek maliyet yöntemidir.
- Maliyet hesapları Money/BCMath/string ile yapılır; PHP float yoktur.
- Stok miktarı decimal(18,3), maliyet/tutar decimal(18,4), dönüşüm decimal(18,6).
- Period kilidi `document_date` / hareket iş tarihi üzerinden kontrol edilir.
- Durum değiştiren işlemler idempotency key ile korunur.
- Balance/cost kritik satırları deterministik sırada lockForUpdate ile kilitlenir.
- Transaction deadlock için attempts=3 kullanır.
- İhlal edilemez kurallar CHECK constraint ile korunur.
- `stock_balances.quantity` negatif olabilir; reserved/quarantine/consignment negatif olamaz.
- Negatif stok izni ürün bazındadır; negatif rezervasyon izni değildir.
- Rezervasyon yalnız mevcut kullanılabilir miktar kadar oluşturulur.
- Karşılanamayan rezerv miktarı açık siparişte kalır.
- Rezervasyon bir sipariş satırını birden fazla lokasyona dağıtabilir.
- Sevkiyat/irsaliye ilgili rezervasyonu çözer ve stok çıkışını üretir.
- İrsaliyeden gelen fatura stok çıkışını ikinci kez üretmez.
- İrsaliyesiz doğrudan fatura ileride stok+cari etki üretir.
- Karantina miktarı satılabilir/rezerve edilebilir stoktan düşülür.
- Konsinye/numune fiziksel stokta kalır ama kullanılabiliri azaltır.
- Sayım farkı kullanıcı onayı olmadan stok hareketi üretmez.
- Transfer çıkış ve giriş aynı business transaction zincirinde izlenir.
- Yolda transfer hedef stoğa girmiş sayılmaz.
- Koli etiketi `warehouse_slip`/ambar fişi kolilerine bağlıdır; irsaliye etiketi değildir.
- Etiket yazdırma PrintManager üzerinden, marka/model bağımsız çalışır.
- Stok/cari bakiye cache edilmez.
- Her türetilmiş stok verisi ilgili `integrity:` kontrolüne sahiptir.
- Testler gerçek PostgreSQL'de çalışır.
- Filament/Tailwind yok; Livewire 3 + tek CSS.
- cost.view yoksa maliyet UI/payload/exportta üretilmez.
- Yeni iş kararı gerekiyorsa uydurulmaz.

### Kabul/test matrisi
- [ ] `stock_movements` toplamı ile `stock_balances.quantity` eşleşir.
- [ ] Tek hareket aynı idempotency key ile iki kez yazılmaz.
- [ ] İki eşzamanlı giriş moving average'ı bozmaz.
- [ ] İki eşzamanlı çıkış balance_after sırasını bozmaz.
- [ ] Rollback hareket+bakiye+maliyet yan etkilerini geri alır.
- [ ] Period DB'ler arasında stok sızıntısı olmaz.
- [ ] company_id kolonunun olmadığını schema introspection doğrular.
- [ ] Master user'a period FK olmadığını schema introspection doğrular.
- [ ] product/location period FK öksüz kaydı engeller.
- [ ] quantity > 0 CHECK çalışır.
- [ ] direction CHECK çalışır.
- [ ] unit_cost negatif CHECK reddeder.
- [ ] reserved/quarantine/consignment non-negative CHECK çalışır.
- [ ] Temel birim dönüşümü doğru base_quantity üretir.
- [ ] Eksik dönüşüm işlem engeller.
- [ ] Float kullanılmadan beklenen maliyet string olarak eşleşir.
- [ ] Negatif stok kapalı üründe yetersiz çıkış engellenir.
- [ ] Negatif stok açık üründe uyarı ile hareket oluşur.
- [ ] Rezervasyon fiziksel quantity'yi düşürmez.
- [ ] Rezervasyon kullanılabiliri azaltır.
- [ ] Rezervasyon mevcut kullanılabilirden fazla oluşmaz.
- [ ] Çok lokasyon rezervasyon toplamı istenen/karşılanabilen miktarla eşleşir.
- [ ] Rezerv çözümü reserved değerini doğru azaltır.
- [ ] Sevk consume sonrası stok bir kez düşer.
- [ ] Karantinadaki miktar rezervasyona girmez.
- [ ] Transfer kaynak düşüş/hedef giriş zinciri tutarlıdır.
- [ ] Sayım onaysız stok hareketi üretmez.
- [ ] Sayım onaylı fark doğru direction ile hareket üretir.
- [ ] Ambar fişi koli sayısı ve 1/N etiket bilgisi tutarlı.
- [ ] cost.view olmayan kullanıcı maliyet verisi görmez.
- [ ] integrity:stock farkı raporlar, otomatik düzeltmez.
- [ ] integrity:costs moving average farkını yakalar.
- [ ] integrity:reservations farkı yakalar.
- [ ] integrity:quarantine farkı yakalar.
- [ ] integrity:units base_quantity farkını yakalar.
- [ ] Queue/session bağımsız period context testi geçer.
- [ ] Kapalı ay stok hareketi engellenir.
- [ ] Activity log kritik manuel düzeltmeyi kaydeder.
- [ ] Pint geçer.
- [ ] Larastan level 6 geçer.
- [ ] Pest gerçek PostgreSQL'de geçer.

### Claude kod inceleme matrisi
- [ ] Migration connection=period.
- [ ] company_id yok.
- [ ] BelongsToCompany/global scope yok.
- [ ] Cross-DB user FK yok.
- [ ] product/location FK period içi gerçek.
- [ ] RecordStockMovement tek stok writer.
- [ ] stock_balances business kodundan direct update edilmiyor.
- [ ] base_quantity/frozen conversion_factor kuralı korunuyor.
- [ ] Money/BCMath var, float yok.
- [ ] Lock sırası deterministik.
- [ ] Idempotency var.
- [ ] attempts=3 var.
- [ ] CHECK constraints var.
- [ ] document_date/iş tarihi doğru.
- [ ] Period lock çağrılıyor.
- [ ] Actor snapshot var.
- [ ] cost.view veri üretimini engelliyor.
- [ ] Cache stok/cari bakiyesini kapsamıyor.
- [ ] Integrity command ilgili türetilmiş tabloyu kapsıyor.
- [ ] Rezervasyon negatif değil.
- [ ] Çok lokasyon dağılımı kayıt bazında izleniyor.
- [ ] İrsaliye stok etkisine uygun contract korunuyor.
- [ ] Fatura ikinci stok etkisi üretmemeye hazırlanıyor.
- [ ] Warehouse slip carton label ilişkisi doğru.
- [ ] PrintManager dışına yazıcı kodu sızmıyor.
- [ ] Test iki period DB kullanıyor.
- [ ] Gerçek PostgreSQL kullanılıyor.
- [ ] UI boş/örneksiz açılıyor.
- [ ] Liste ilk satır seçili değil.
- [ ] Yeni CSS/Tailwind/Filament yok.
- [ ] Görev dışı refactor yok.

## Kabul ölçütü
- Gönderince kaynak azalıyor, hedef değişmiyor
- Teslim alınca hedef artıyor
- Ortalama maliyet hiç değişmiyor
- Kısmi teslim kalanı doğru takip ediyor
- Aynı lokasyona transfer reddediliyor


### Ek Claude doğrulama senaryoları
- [ ] 01. Schema introspection ile company_id olmadığını doğrula.
- [ ] 02. RecordStockMovement dışında stock_balances write çağrısı olmadığını ara.
- [ ] 03. Base unit miktarını elle hesaplanan değerle karşılaştır.
- [ ] 04. Eksik conversion factor DomainException üretmeli.
- [ ] 05. Aynı product/location için concurrent hareketi test et.
- [ ] 06. Deadlock retry sonrası tek hareket oluştuğunu doğrula.
- [ ] 07. Balance_after ve avg_cost_after yeniden hesaplanınca eşleşmeli.
- [ ] 08. Negative stock flag davranışını iki ürünle test et.
- [ ] 09. Reserved hiçbir zaman negatif olmamalı.
- [ ] 10. Çok lokasyon reservation toplamı doğru olmalı.
- [ ] 11. Consume sonrası aynı rezerv ikinci kez tüketilememeli.
- [ ] 12. Karantina miktarı available hesabında düşülmeli.
- [ ] 13. Consignment reserved available hesabında düşülmeli.
- [ ] 14. Transfer yolda durumunda hedef quantity artmamalı.
- [ ] 15. Sayım snapshot ile mevcut balance farkı açıkça raporlanmalı.
- [ ] 16. Warehouse slip carton label sıra/toplam tutarlı olmalı.
- [ ] 17. PrintManager marka bağımsız profile çözümlemeli.
- [ ] 18. cost.view olmayan export maliyet alanı taşımamalı.
- [ ] 19. Integrity command farkı otomatik düzeltmemeli.
- [ ] 20. Dönem devri opening cost closing average ile aynı olmalı.
- [ ] 21. Schema introspection ile company_id olmadığını doğrula.
- [ ] 22. RecordStockMovement dışında stock_balances write çağrısı olmadığını ara.
- [ ] 23. Base unit miktarını elle hesaplanan değerle karşılaştır.
- [ ] 24. Eksik conversion factor DomainException üretmeli.
- [ ] 25. Aynı product/location için concurrent hareketi test et.
- [ ] 26. Deadlock retry sonrası tek hareket oluştuğunu doğrula.
- [ ] 27. Balance_after ve avg_cost_after yeniden hesaplanınca eşleşmeli.
- [ ] 28. Negative stock flag davranışını iki ürünle test et.
- [ ] 29. Reserved hiçbir zaman negatif olmamalı.
- [ ] 30. Çok lokasyon reservation toplamı doğru olmalı.
- [ ] 31. Consume sonrası aynı rezerv ikinci kez tüketilememeli.
- [ ] 32. Karantina miktarı available hesabında düşülmeli.
- [ ] 33. Consignment reserved available hesabında düşülmeli.
- [ ] 34. Transfer yolda durumunda hedef quantity artmamalı.
- [ ] 35. Sayım snapshot ile mevcut balance farkı açıkça raporlanmalı.
- [ ] 36. Warehouse slip carton label sıra/toplam tutarlı olmalı.
- [ ] 37. PrintManager marka bağımsız profile çözümlemeli.
- [ ] 38. cost.view olmayan export maliyet alanı taşımamalı.
- [ ] 39. Integrity command farkı otomatik düzeltmemeli.
- [ ] 40. Dönem devri opening cost closing average ile aynı olmalı.
- [ ] 41. Schema introspection ile company_id olmadığını doğrula.
- [ ] 42. RecordStockMovement dışında stock_balances write çağrısı olmadığını ara.
- [ ] 43. Base unit miktarını elle hesaplanan değerle karşılaştır.
- [ ] 44. Eksik conversion factor DomainException üretmeli.
- [ ] 45. Aynı product/location için concurrent hareketi test et.
- [ ] 46. Deadlock retry sonrası tek hareket oluştuğunu doğrula.
- [ ] 47. Balance_after ve avg_cost_after yeniden hesaplanınca eşleşmeli.
- [ ] 48. Negative stock flag davranışını iki ürünle test et.
- [ ] 49. Reserved hiçbir zaman negatif olmamalı.
- [ ] 50. Çok lokasyon reservation toplamı doğru olmalı.
- [ ] 51. Consume sonrası aynı rezerv ikinci kez tüketilememeli.
- [ ] 52. Karantina miktarı available hesabında düşülmeli.
- [ ] 53. Consignment reserved available hesabında düşülmeli.
- [ ] 54. Transfer yolda durumunda hedef quantity artmamalı.
- [ ] 55. Sayım snapshot ile mevcut balance farkı açıkça raporlanmalı.
- [ ] 56. Warehouse slip carton label sıra/toplam tutarlı olmalı.
- [ ] 57. PrintManager marka bağımsız profile çözümlemeli.
- [ ] 58. cost.view olmayan export maliyet alanı taşımamalı.
- [ ] 59. Integrity command farkı otomatik düzeltmemeli.
- [ ] 60. Dönem devri opening cost closing average ile aynı olmalı.
- [ ] 61. Schema introspection ile company_id olmadığını doğrula.
- [ ] 62. RecordStockMovement dışında stock_balances write çağrısı olmadığını ara.
- [ ] 63. Base unit miktarını elle hesaplanan değerle karşılaştır.
- [ ] 64. Eksik conversion factor DomainException üretmeli.
- [ ] 65. Aynı product/location için concurrent hareketi test et.
- [ ] 66. Deadlock retry sonrası tek hareket oluştuğunu doğrula.
- [ ] 67. Balance_after ve avg_cost_after yeniden hesaplanınca eşleşmeli.
- [ ] 68. Negative stock flag davranışını iki ürünle test et.
- [ ] 69. Reserved hiçbir zaman negatif olmamalı.
- [ ] 70. Çok lokasyon reservation toplamı doğru olmalı.
- [ ] 71. Consume sonrası aynı rezerv ikinci kez tüketilememeli.
- [ ] 72. Karantina miktarı available hesabında düşülmeli.
- [ ] 73. Consignment reserved available hesabında düşülmeli.
- [ ] 74. Transfer yolda durumunda hedef quantity artmamalı.
- [ ] 75. Sayım snapshot ile mevcut balance farkı açıkça raporlanmalı.
- [ ] 76. Warehouse slip carton label sıra/toplam tutarlı olmalı.
- [ ] 77. PrintManager marka bağımsız profile çözümlemeli.
- [ ] 78. cost.view olmayan export maliyet alanı taşımamalı.
- [ ] 79. Integrity command farkı otomatik düzeltmemeli.
- [ ] 80. Dönem devri opening cost closing average ile aynı olmalı.
- [ ] 81. Schema introspection ile company_id olmadığını doğrula.
- [ ] 82. RecordStockMovement dışında stock_balances write çağrısı olmadığını ara.
- [ ] 83. Base unit miktarını elle hesaplanan değerle karşılaştır.
- [ ] 84. Eksik conversion factor DomainException üretmeli.
- [ ] 85. Aynı product/location için concurrent hareketi test et.
- [ ] 86. Deadlock retry sonrası tek hareket oluştuğunu doğrula.
- [ ] 87. Balance_after ve avg_cost_after yeniden hesaplanınca eşleşmeli.
- [ ] 88. Negative stock flag davranışını iki ürünle test et.
- [ ] 89. Reserved hiçbir zaman negatif olmamalı.
- [ ] 90. Çok lokasyon reservation toplamı doğru olmalı.
- [ ] 91. Consume sonrası aynı rezerv ikinci kez tüketilememeli.
- [ ] 92. Karantina miktarı available hesabında düşülmeli.
- [ ] 93. Consignment reserved available hesabında düşülmeli.
- [ ] 94. Transfer yolda durumunda hedef quantity artmamalı.
- [ ] 95. Sayım snapshot ile mevcut balance farkı açıkça raporlanmalı.
- [ ] 96. Warehouse slip carton label sıra/toplam tutarlı olmalı.
- [ ] 97. PrintManager marka bağımsız profile çözümlemeli.
- [ ] 98. cost.view olmayan export maliyet alanı taşımamalı.
- [ ] 99. Integrity command farkı otomatik düzeltmemeli.
- [ ] 100. Dönem devri opening cost closing average ile aynı olmalı.
- [ ] 101. Schema introspection ile company_id olmadığını doğrula.
- [ ] 102. RecordStockMovement dışında stock_balances write çağrısı olmadığını ara.
- [ ] 103. Base unit miktarını elle hesaplanan değerle karşılaştır.
- [ ] 104. Eksik conversion factor DomainException üretmeli.
- [ ] 105. Aynı product/location için concurrent hareketi test et.
- [ ] 106. Deadlock retry sonrası tek hareket oluştuğunu doğrula.
- [ ] 107. Balance_after ve avg_cost_after yeniden hesaplanınca eşleşmeli.
- [ ] 108. Negative stock flag davranışını iki ürünle test et.
- [ ] 109. Reserved hiçbir zaman negatif olmamalı.
- [ ] 110. Çok lokasyon reservation toplamı doğru olmalı.
- [ ] 111. Consume sonrası aynı rezerv ikinci kez tüketilememeli.
- [ ] 112. Karantina miktarı available hesabında düşülmeli.
- [ ] 113. Consignment reserved available hesabında düşülmeli.
- [ ] 114. Transfer yolda durumunda hedef quantity artmamalı.
- [ ] 115. Sayım snapshot ile mevcut balance farkı açıkça raporlanmalı.

## İstem
> transfers ve transfer_lines tabloları için migration, modeller, ekranlar,
> gönderme ve teslim alma action'larını yaz. Giriş hareketi çıkışın birim
> maliyetiyle yazılsın ve ortalamayı güncellemesin.
