# Veri bütünlüğü ve doğrulama

Bu sistem stok, cari ve kasanın **tek kaydıdır**. Arkasında düzeltici bir
defter yok. Sessizce bozulan bir rakam aylar sonra fark edilir ve o zamana
kadar verilen her karar yanlış olur.

Bu yüzden doğrulama **dört katmanda** yapılır.

---

## Katman 1 — Veritabanı kısıtları (hatalı veriyi fiziksel olarak engeller)

Uygulama kodu atlanabilir; veritabanı kısıtı atlanamaz. Kritik kurallar
CHECK kısıtı olarak yazılır.

```sql
-- miktar her zaman pozitif, yön ayrı kolonda
ALTER TABLE stock_movements
  ADD CONSTRAINT sm_quantity_positive CHECK (quantity > 0);

ALTER TABLE stock_movements
  ADD CONSTRAINT sm_direction_valid CHECK (direction IN ('in','out'));

-- birim maliyet negatif olamaz
ALTER TABLE stock_movements
  ADD CONSTRAINT sm_unit_cost_nonneg CHECK (unit_cost >= 0);

-- toplam = miktar x birim maliyet (yuvarlama payı ile)
ALTER TABLE stock_movements
  ADD CONSTRAINT sm_total_matches
  CHECK (abs(total_cost - (quantity * unit_cost)) < 0.01);

-- bakiye alanları negatif olamaz (quantity hariç — negatif stok izinli)
ALTER TABLE stock_balances
  ADD CONSTRAINT sb_reserved_nonneg CHECK (reserved >= 0),
  ADD CONSTRAINT sb_quarantine_nonneg CHECK (quarantine >= 0),
  ADD CONSTRAINT sb_consignment_nonneg CHECK (consignment_reserved >= 0);

-- belge toplamı tutarlı
-- yuvarlama farkı dahil; tolerans 0.0001 (bkz. 17-para-aritmetigi.md)
ALTER TABLE documents
  ADD CONSTRAINT doc_total_matches
  CHECK (abs(grand_total - (tax_base + vat_amount + rounding_difference)) < 0.0001);

ALTER TABLE documents
  ADD CONSTRAINT doc_base_matches
  CHECK (abs(tax_base - (subtotal - discount_amount)) < 0.01);

-- oranlar makul aralıkta
ALTER TABLE document_lines
  ADD CONSTRAINT dl_vat_range CHECK (vat_rate >= 0 AND vat_rate <= 100);

-- dönem ayı geçerli
ALTER TABLE posting_periods
  ADD CONSTRAINT pp_month_valid CHECK (month BETWEEN 1 AND 12);
```

**Kural:** her migration'da, o tablonun ihlal edilemez kuralları CHECK
olarak yazılır. "Uygulama zaten kontrol ediyor" gerekçesi kabul edilmez —
içe aktarma, kuyruk işi ve elle SQL uygulamayı atlar.

---

## Katman 2 — Yazma sonrası doğrulama (aynı işlem içinde)

Belge kesinleştirildikten sonra, **transaction kapanmadan önce**,
türetilmiş durumun beklenenle eşleştiği kontrol edilir.

```php
DB::transaction(function () use ($document) {
    $before = $this->snapshot($document);      // etkilenecek bakiyeler

    $this->post($document);                     // asıl iş

    $this->verify($document, $before);          // <-- eşleşmezse istisna
});
```

`verify()` neye bakar:

| Kontrol | Beklenen |
|---|---|
| Stok bakiyesi | önceki bakiye ± belge miktarı |
| Hareket sayısı | belge satırı sayısı kadar hareket oluştu mu |
| Cari bakiyesi | önceki bakiye ± belge tutarı |
| Belge toplamı | satır toplamlarından yeniden hesaplanınca aynı mı |
| Numara | verildi mi, benzersiz mi |

Eşleşmezse istisna fırlatılır, **transaction geri alınır**, kullanıcı
"işlem tamamlanamadı" uyarısı alır. Yarım yazılmış veri kalmaz.

**Maliyeti:** her belgede birkaç ek sorgu. Karşılığında sessiz bozulma yok.

---

## Katman 3 — Zamanlanmış tutarlılık kontrolü

Gecelik çalışan komutlar. Sorunu oluştuğu gün yakalar, aylar sonra değil.

```bash
php artisan integrity:stock        # hareket toplamı = bakiye
php artisan integrity:contacts     # cari hareket toplamı = bakiye
php artisan integrity:documents    # belge toplamı = satır toplamları
php artisan integrity:numbers      # numara boşluğu / tekrarı
php artisan integrity:all          # hepsi + rapor
```

### integrity:stock

```php
$calculated = StockMovement::query()
    ->where('product_id', $p)->where('location_id', $l)
    ->selectRaw("SUM(CASE WHEN direction='in' THEN quantity ELSE -quantity END) as total")
    ->value('total');

$stored = StockBalance::where(...)->value('quantity');

if (abs($calculated - $stored) > 0.001) {
    $this->report($p, $l, $calculated, $stored);
}
```

### integrity:documents

Her belgenin `subtotal`, `tax_base`, `vat_amount`, `grand_total`
alanlarını satırlardan **yeniden hesaplar** ve saklanan değerle
karşılaştırır. Fark varsa belge numarasıyla raporlar.

### integrity:numbers

Her seri için: numara tekrarı var mı, boşluk var mı. Boşluk
`lockForUpdate` doğru çalışıyorsa oluşmamalı; oluştuysa bir yerde
transaction dışında numara üretilmiş demektir.

## Raporlama

Sonuç `integrity_reports` tablosuna yazılır ve **fark varsa** yöneticiye
bildirim gider. Fark yoksa sessiz kalır.

```php
// integrity_reports (DÖNEM veritabanı)
// id, check_name, run_at, checked_count, mismatch_count, details(json), duration_ms
```

Ana sayfada "son bütünlük kontrolü: 30.09.2026 03:00 · fark yok" satırı
gösterilir. Üç günden eski ya da farklı ise kırmızı uyarı.

---

## Katman 4 — Düzeltme

Fark bulunduğunda **otomatik düzeltme yapılmaz.** Sebebi bilinmeden
düzeltmek, asıl hatayı gizler.

Ayarlar altında **Bütünlük Kontrolü** ekranı:
- Son çalıştırmaların listesi
- Farkların detayı (ürün, lokasyon, hesaplanan, saklanan, fark)
- "Yeniden hesapla" eylemi — yalnızca Yönetici, gerekçe ister,
  `activity_log`'a düşer
- Yeniden hesaplama bakiyeyi hareket toplamına eşitler; **hareketler
  gerçektir, bakiye türetilmiştir**

---

## Yazmanın gerçekten olduğunu anlamak

Laravel sessizce başarısız olmaz ama şu üç durum gözden kaçar:

**1. `save()` dönüş değeri kontrol edilmiyor.** Observer `false`
döndürürse kayıt yazılmaz ve istisna da fırlatılmaz.

```php
if (! $model->save()) {
    throw new \RuntimeException('Kayıt yazılamadı: '.$model::class);
}
```

**2. Kilitlenme (deadlock) sessizce yeniden denenmiyor.**

```php
DB::transaction(function () { ... }, attempts: 3);
```

**3. Toplu işlemde kısmi başarı.** `insert()` ile toplu yazmada bazı
satırlar geçmezse fark edilmez. İçe aktarmada yazılan satır sayısı
beklenenle karşılaştırılır.

---

## KAPSAM — neyin kontrol edildiği, neyin edilmediği

Bütünlük kontrolü **türetilmiş veya kopyalanmış her değer** için gerekir.
Tek kaynaktan okunan veride risk yoktur; iki yerde duran her şeyde vardır.

### Kural

> **Türetilmiş bir tablo veya kopyalanmış bir alan eklediysen, aynı
> görevde onun `integrity:` kontrolünü de yazacaksın.**

Bu, her fazın bitiş ölçütüne dahildir. Kontrolü yazılmamış türetilmiş
veri, sessiz bozulmaya açık bir alandır.

### Kapsam matrisi

| Veri | Risk | Kontrol | Faz |
|---|---|---|---|
| `stock_balances` ↔ `stock_movements` toplamı | Bakiye kayar | `integrity:stock` | **0 — yazıldı** |
| Belge toplamı ↔ satır toplamları | Fatura tutarı yanlış | `integrity:documents` | **0 — yazıldı** |
| Cari bakiye ↔ `contact_transactions` | Bakiye yanlış | `integrity:contacts` | **0 — yazıldı** |
| Numara boşluğu / tekrarı | Belge numarası çakışır | `integrity:numbers` | **0 — yazıldı** |
| `product_costs.moving_average` ↔ son hareketin `avg_cost_after` | Maliyet kayar, kârlılık yanlış | `integrity:costs` | **2 — EKSİK** |
| `stock_balances.reserved` ↔ aktif `stock_reservations` toplamı | Kullanılabilir yanlış, çift satış | `integrity:reservations` | **2 — EKSİK** |
| `stock_balances.quarantine` ↔ bekleyen `quarantine_entries` | Karantina miktarı kayar | `integrity:quarantine` | **2 — EKSİK** |
| Belgedeki `product_code` / `contact_code` ↔ master'daki kart | Kart silinmiş/değişmiş, belge öksüz | `integrity:references` | **3 — EKSİK** |
| Kısmi sevk: `quantity` ↔ Σ `received_quantity` | Kalan miktar yanlış | `integrity:partials` | **3 — EKSİK** |
| Kasa/banka bakiyesi ↔ hareket toplamı | Kasa tutmaz | `integrity:cash` | **5 — EKSİK** |
| Çek/senet durumu ↔ cari hareketi | Tahsil edilmiş çek hâlâ portföyde | `integrity:securities` | **5 — EKSİK** |
| `attachments` kaydı ↔ diskteki dosya | Kayıt var dosya yok, ya da öksüz dosya | `integrity:files` | **0 — EKSİK** |
| İthalat maliyet dağıtımı ↔ kalem toplamı | Dağıtılan ≠ toplam masraf | `integrity:landed_cost` | **7 — EKSİK** |
| Üretim: çıkan malzeme ↔ mamul maliyeti | Mamul maliyeti yanlış | `integrity:production` | **8 — EKSİK** |
| Kanala gönderilen stok ↔ gerçek kullanılabilir | Pazaryerinde çift satış | `integrity:channels` | **9 — EKSİK** |
| Belge satırı: `base_quantity` ↔ `quantity × conversion_factor` | Stok birimi kayar, miktar katlanır | `integrity:units` | **1 — EKSİK** |
| Devir: kaynak kapanış ↔ hedef açılış | Yıl başında bakiye kayar | `integrity:carry` | **11b — EKSİK** |
| Yedek geri yükleme provası | Yedek bozuk çıkar | elle, aylık | **0 — elle** |

**Şu an 4 kontrol yazılı, 13 kontrol eksik.** Eksikler ilgili fazlarda
yazılacak; her fazın bitiş ölçütüne eklendi.

### Özellikle riskli üçü

**`integrity:references`** — veritabanları arası yabancı anahtar yok.
Master'da bir ürün pasife alınır veya kodu değişirse, dönem
veritabanındaki belgeler öksüz kalır. Belgeye kod ve ad kopyalandığı
için belge okunabilir kalır ama ürün kartına gidilemez. Kontrol, öksüz
referansları listeler.

**`integrity:reservations`** — rezerv toplamı bakiyedeki `reserved`
alanıyla uyuşmazsa kullanılabilir miktar yanlış olur ve **çift satış**
yapılır. Sipariş iptalinde rezerv çözülmezse sessizce birikir.

**`integrity:carry`** — devir sonrası hedef dönemin açılış toplamı,
kaynak dönemin kapanış toplamına eşit olmalı. Eşit değilse yılın tamamı
yanlış başlar ve bu aylar sonra fark edilir.

### Kontrol edilmesine gerek olmayanlar

Tek kaynaktan okunan veride kontrol gereksizdir: master'daki kart
bilgileri, `stock_movements` satırlarının kendisi, `activity_log`,
set ürün satılabilirliği (anlık hesaplanıyor, saklanmıyor).

---

## Test tarafı

Her Action için en az bir test: **yazdıktan sonra veritabanından geri
okuyup** beklenen değeri doğrular. `assertDatabaseHas` yeterli değildir;
hesaplanan alanlar da kontrol edilir.

```php
it('fatura kesinlesince stok ve cari dogru yazilir', function () {
    $doc = postInvoice(product: $p, qty: 5, price: 100);

    expect(StockBalance::find(...)->quantity)->toBe(95.0);       // 100 - 5
    expect(ContactTransaction::sum('debit'))->toBe(600.0);       // 500 + KDV
    expect(StockMovement::count())->toBe(1);
    expect($doc->fresh()->number)->toMatch('/^SF-\d{4}-\d{5}$/');
});
