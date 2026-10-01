# G-202 — RecordStockMovement (tek yazma noktası)

**Veritabanı: DÖNEM.** Model `PeriodModel`'den türer, `company_id` kolonu
yoktur, migration `database/migrations/period/` altına yazılır.


## Amaç

Stoğa yazan **tek** action. Bütün Faz 2 bunun etrafında kurulur; satış,
alış, üretim, ithalat hep bunu çağırır.

## Önkoşul
G-201

## Dokunulacak dosyalar
- `app/Actions/Stock/RecordStockMovement.php`
- `app/Actions/Stock/UpdateMovingAverage.php`
- `app/Exceptions/NegativeStockException.php`
- `app/DataObjects/StockMovementData.php`


## Şema / Kod

Mevcut şema/kod örnekleri aşağıdaki kanonik stok sözleşmesiyle birlikte uygulanır; çelişkide kanonik sözleşme üstündür.

## Action

```php
final class RecordStockMovement
{
    public function __construct(
        private EnsurePeriodOpen $ensurePeriodOpen,
        private UpdateMovingAverage $updateAverage,
    ) {}

    public function handle(StockMovementData $data): StockMovement
    {
        return DB::transaction(function () use ($data) {

            // 1. Dönem kilidi
            $this->ensurePeriodOpen->handle($data->movementDate);

            // 2. Bakiye satırını kilitle (eşzamanlılık)
            $balance = StockBalance::query()
                ->where('product_id', $data->productId)
                ->where('location_id', $data->locationId)
                ->lockForUpdate()
                ->firstOrCreate([
                    'product_id'  => $data->productId,
                    'location_id' => $data->locationId,
                ]);

            $product = Product::findOrFail($data->productId);

            // 3. Negatif stok kontrolü
            if ($data->direction === 'out') {
                $after = $balance->quantity - $data->quantity;
                if ($after < 0 && ! $product->allow_negative_stock) {
                    throw new NegativeStockException(
                        "{$product->code} için stok yetersiz. Mevcut: {$balance->quantity}"
                    );
                }
            }

            // 4. Birim maliyet
            $cost = ProductCost::firstOrCreate(['product_id' => $product->id]);

            if ($data->direction === 'in') {
                // giriş: verilen fiyat; ortalama güncellenir
                $unitCost = $data->unitCost;
                $newAvg = $data->updatesAverage
                    ? $this->updateAverage->handle($product, $data->quantity, $unitCost)
                    : $cost->moving_average;
            } else {
                // çıkış: o anki ortalama; ortalama DEĞİŞMEZ
                $unitCost = $cost->moving_average;
                $newAvg   = $cost->moving_average;
            }

            // 5. Bakiyeyi güncelle
            $balance->quantity += ($data->direction === 'in' ? 1 : -1) * $data->quantity;
            $balance->save();

            // 6. Hareketi yaz
            return StockMovement::create([
                'product_id'     => $data->productId,
                'location_id'    => $data->locationId,
                'movement_date'  => $data->movementDate,
                'direction'      => $data->direction,
                'reason'         => $data->reason,
                'quantity'       => $data->quantity,          // HER ZAMAN POZİTİF
                'unit_cost'      => $unitCost,
                'total_cost'     => $data->quantity * $unitCost,
                'balance_after'  => $balance->quantity,
                'avg_cost_after' => $newAvg,
                'document_type'  => $data->documentType,
                'document_id'    => $data->documentId,
                'document_no'    => $data->documentNo,
                'note'           => $data->note,
                'created_by'     => auth()->id(),
            ]);
        });
    }
}
```

## Kritik noktalar

**`lockForUpdate()` atlanamaz.** İki kullanıcı aynı ürünü aynı anda
satarsa ikisi de aynı bakiyeyi okur ve stok yanlış kalır.

**Çıkışta ortalama değişmez.** Çıkış maliyeti o anki ortalamadır.

**`quantity` her zaman pozitiftir**, yön `direction`'dadır.

**Transfer iki hareket yazar** ve `updatesAverage = false` geçer —
transfer maliyeti değiştirmez.

**Her şey tek transaction içinde.** Bakiye güncellendi ama hareket
yazılmadıysa veri bozulur.


## Kurallar

### Göreve özel kararlar
- RecordStockMovement stok yazan tek Action'dır.
- Post-write verify aynı transaction içinde balance/cost sonuçlarını kontrol eder.


### Uygulama ayrıntıları
- `RecordStockMovement` stok yazan tek Action'dır; hareket + bakiye + maliyet yan etkilerini tek transaction'da yönetir.
- İşlem öncesi period açık kontrolü ve ürün/lokasyon doğrulaması yapılır.
- Kritik product/location bakiye satırı `lockForUpdate` ile korunur; deadlock retry `attempts: 3` kullanır.
- Yazım sonrası aynı transaction içinde balance/cost doğrulaması başarısızsa rollback olur.

## Kabul ölçütü

- Giriş: bakiye artar, ortalama güncellenir
- Çıkış: bakiye azalır, ortalama **değişmez**
- Negatif stok izinsizken çıkış `NegativeStockException` fırlatır
- İzinliyken geçer, bakiye negatife düşer
- Kapalı dönem `PeriodClosedException` fırlatır
- Eşzamanlı 10 çıkış denemesinde bakiye tutarlı kalır
- `balance_after` ve `avg_cost_after` doğru saklanır


## İstem
> RecordStockMovement action'ını, StockMovementData nesnesini,
> UpdateMovingAverage action'ını ve NegativeStockException sınıfını
> yukarıdaki kodla birebir yaz. lockForUpdate ve DB::transaction
> satırlarını kesinlikle atlama. Çıkışta ortalama güncellenmesin.
