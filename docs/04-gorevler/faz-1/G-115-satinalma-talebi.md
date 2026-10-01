# G-115 — Satınalma talebi ve teklif toplama (basit)

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer.

## Amaç
Satınalma döngüsü doğrudan siparişle başlıyordu. Önüne iki basit halka
ekleniyor: **talep** ve **tedarikçi teklifi karşılaştırma**.

Basit tutulur: onay zinciri, bütçe kontrolü, RFQ e-postası **yok**.

## Önkoşul
G-104 (cari), G-106 (ürün)

## Şemalar

```php
// purchase_requests
$table->id();
$table->string('number', 40)->nullable();          // kesinleşince verilir
$table->date('request_date');
$table->foreignId('requested_by')->constrained('users');
$table->string('department', 60)->nullable();
$table->date('needed_by')->nullable();
$table->string('status', 15)->default('draft');     // draft|open|quoted|ordered|cancelled
$table->text('note')->nullable();
$table->timestamps();

// purchase_request_lines
$table->foreignId('purchase_request_id')->constrained()->cascadeOnDelete();
$table->foreignId('product_id')->constrained();
$table->string('product_code', 40);
$table->decimal('quantity', 18, 3);
$table->foreignId('unit_id')->constrained('units');
$table->text('note')->nullable();

// supplier_quotes  — tedarikçiden gelen teklif
$table->foreignId('purchase_request_id')->constrained();
$table->foreignId('contact_id')->constrained();     // tedarikçi
$table->date('quote_date');
$table->date('valid_until')->nullable();
$table->char('currency', 3)->default('TRY');
$table->decimal('exchange_rate', 18, 6)->default(1);
$table->unsignedSmallInteger('delivery_days')->nullable();
$table->boolean('is_selected')->default(false);
$table->text('note')->nullable();

// supplier_quote_lines
$table->foreignId('supplier_quote_id')->constrained()->cascadeOnDelete();
$table->foreignId('purchase_request_line_id')->constrained();
$table->decimal('unit_price', 18, 4);               // KDV hariç
$table->decimal('quantity', 18, 3);
```

## Akış

```
1. Talep açılır: ürün + miktar + ihtiyaç tarihi        → draft
2. "Teklif İste" → status = open, numara verilir
3. Tedarikçi teklifleri ELLE girilir (e-posta/telefonla gelen)
4. Karşılaştırma ekranı: satır bazında en ucuz vurgulanır
5. Bir teklif seçilir (is_selected)                    → quoted
6. "Siparişe Dönüştür" → satınalma siparişi oluşur     → ordered
```

## Karşılaştırma ekranı

Satırlar ürün, kolonlar tedarikçi. Her hücrede birim fiyat ve toplam.
En ucuz hücre yeşil. Altta tedarikçi bazında genel toplam, teslim süresi
ve para birimi. Farklı para birimindeki teklifler **belge tarihinin
kuruyla** TRY'ye çevrilerek karşılaştırılır.

## Kurallar
- Talep kesinleşmeden teklif girilemez
- Bir talepten **tek sipariş** çıkar; kısmi sipariş isteniyorsa talep bölünür
- Seçilmeyen teklifler saklanır (geçmiş fiyat bilgisi)
- Siparişe dönüşünce talep `ordered`, değiştirilemez
- Fiyat girişinde maliyet sapma uyarısı **çalışmaz** (henüz alış değil)

## Kabul ölçütü
- Talep açılıyor, numara kesinleşmede veriliyor
- Üç tedarikçi teklifi girilip karşılaştırılıyor, en ucuz vurgulanıyor
- Farklı para birimi TRY'ye çevrilerek kıyaslanıyor
- Seçilen tekliften sipariş oluşuyor, satırlar ve fiyatlar taşınıyor
- Talep `ordered` olunca değiştirilemiyor

## İstem
> purchase_requests, purchase_request_lines, supplier_quotes ve
> supplier_quote_lines tabloları için migration, modeller, talep ekranı,
> teklif giriş ekranı, karşılaştırma ekranı ve siparişe dönüştürme
> action'ını yaz. Onay zinciri, bütçe kontrolü veya e-posta gönderimi
> EKLEME. Para birimi farkını belge tarihinin kuruyla çöz.
