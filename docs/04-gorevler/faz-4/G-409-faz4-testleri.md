# G-409 — Faz 4 bütünleşik testler

## Amaç

Faz 4 satınalma, teklif seçimi, kısmi teslim/fatura, döviz, stok, maliyet, cari, ters kayıt ve eşzamanlılık sözleşmelerini gerçek PostgreSQL üzerinde bütünleşik doğrulamak.

## Önkoşul

G-401…G-408 tamamlanmış olmalı.

## Dokunulacak dosyalar

- `tests/Feature/Purchasing/Faz4PurchasingFlowTest.php`
- `tests/Feature/Purchasing/Faz4ConcurrencyTest.php`
- `tests/Feature/Purchasing/Faz4IntegrityTest.php`

## Şema / Kod

Yeni production şeması yok. Testler Faz 4 belgeleri ve integrity komutlarını doğrular.

## Test kapsamı

### Talep / teklif

- supplier'sız purchase_request,
- bir request'e çok supplier_quote,
- belge bazlı seçim,
- satır bazlı farklı supplier seçimi,
- `purchasing.quote.select` izni,
- otomatik winner olmaması,
- selection audit,
- selection'ın ikinci kez order'a dönüşmemesi.

### Sipariş / mal kabul

- direct purchase_order,
- selection'lardan supplier bazlı ayrı order,
- kısmi receipt,
- cancelled remainder,
- goods_receipt stock/contact/cost etkisiz,
- concurrent receipt remaining aşımı yok.

### Alış faturası

- direct stock-only purchase_invoice stock in + contact credit,
- service-only purchase_invoice contact credit/KDV/toplam üretir; stock movement/moving-average üretmez,
- mixed stock+service purchase_invoice cari credit'i tam grand_total üzerinden üretir; yalnız stock satırlar stock in + moving average üretir,
- receipt-source invoice stock ilk kez invoice'da,
- partial receipt invoice 60/40,
- multi-receipt same supplier/currency merge,
- farklı supplier/currency merge reddi,
- idempotency.

### Döviz / maliyet

- TRY kur=1,
- USD/EUR frozen exchange_rate,
- dövizli alışın contact transaction tutarı base currency karşılığı,
- posted sonrası kur değişince maliyet değişmiyor,
- line/document discount sonrası VAT hariç inventory cost,
- base_quantity başına unit_cost,
- hareketli ortalama elle beklenen değer,
- mevcut stok <=0 giriş davranışı,
- ±%25 sapma uyarı+audit ve blok olmaması,
- float dönüşümü olmaması.

### Cari / ödeme sınırı

- purchase_invoice supplier credit temel para biriminde doğru frozen-kur karşılığıyla,
- Faz 4'te cash/bank payment movement oluşmaması,
- contact balance formülüyle borcun doğru yönde görünmesi.

### Reverse

- stock-only purchase_invoice inverse stock out + debit,
- service-only purchase_invoice reverse yalnız debit; stock movement yok,
- mixed purchase_invoice reverse yalnız stock satırları out yapar ve tam belge tutarını debit ile tersler,
- goods_receipt reverse stock/cari etkisiz,
- duplicate reverse engeli,
- reversed child partial toplamdan çıkar.

### Concurrency

- number series,
- purchase_quote selection optimistic lock,
- order/receipt/invoice source remaining lock,
- product cost lock,
- deadlock retry idempotency.

### Integrity

- integrity:purchasing,
- integrity:documents,
- integrity:stock,
- integrity:contacts,
- integrity:units.

Fark otomatik düzeltilmez.

## Kurallar

- Testler gerçek PostgreSQL kullanır; SQLite yok.
- Beklenen parasal değerler production hesap fonksiyonundan türetilmez.
- İki fiziksel period DB ile izolasyon doğrulanır.
- Faz 4'te ödeme, kalite veya e-belge kapsamı eklenmez.

## Kabul ölçütü

- Tüm Faz 4 test suite yeşil.
- Pint yeşil.
- Larastan level 6 yeni hata yok.
- Period DB'ler arası veri sızıntısı yok.
- K-086…K-091 ve K-257 ile çelişen davranış yok.
- Yeni açık ürün kararı kod içinde uydurulmamış.

## İstem

> Faz 4 için gerçek PostgreSQL bütünleşik/concurrency/integrity testlerini yaz. K-086…K-091 ve Faz 4 iş kurallarının her kritik etkisini bağımsız beklenen değerlerle doğrula.
