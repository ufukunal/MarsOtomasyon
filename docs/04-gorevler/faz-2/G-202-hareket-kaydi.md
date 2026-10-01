# G-202 — RecordStockMovement (tek yazma noktası)

**Veritabanı: DÖNEM.**

## Amaç

Stok miktarı ve maliyet yan etkilerinin tek yazma kapısını oluşturmak. Satış, alış, transfer, üretim ve ithalat stok değiştirirken doğrudan `stock_balances` yazmaz; `RecordStockMovement` çağırır.

## Önkoşul

G-201.

## Dokunulacak dosyalar

- `app/Actions/Stock/RecordStockMovement.php`
- `app/DataObjects/StockMovementData.php`
- `app/Exceptions/NegativeStockException.php`
- `tests/Feature/Stock/RecordStockMovementTest.php`

## Şema / Kod

`StockMovementData` miktar/maliyet değerlerini **string** taşır:

```php
final readonly class StockMovementData
{
    public function __construct(
        public int $productId,
        public int $locationId,
        public string $movementDate,
        public string $direction,      // in | out
        public string $reason,
        public string $quantity,       // temel birim, decimal string
        public ?string $unitCost,
        public bool $updatesAverage,
        public ?string $documentType,
        public ?int $documentId,
        public ?string $documentNo,
        public ?string $note,
        public ?int $actorUserId,
        public ?string $actorUserName,
    ) {}
}
```

Action'ın kritik aritmetiği PHP operatörleriyle değil BCMath ile yapılır:

```php
final class RecordStockMovement
{
    public function __construct(
        private EnsurePeriodOpen $ensurePeriodOpen,
        private UpdateMovingAverage $updateAverage,
    ) {}

    public function handle(StockMovementData $data): StockMovement
    {
        return DB::connection('period')->transaction(function () use ($data) {
            $this->ensurePeriodOpen->handle($data->movementDate);

            StockBalance::query()->firstOrCreate(
                ['product_id' => $data->productId, 'location_id' => $data->locationId],
                ['quantity' => '0.000', 'reserved' => '0.000',
                 'consignment_reserved' => '0.000', 'quarantine' => '0.000']
            );

            $balance = StockBalance::query()
                ->where('product_id', $data->productId)
                ->where('location_id', $data->locationId)
                ->lockForUpdate()
                ->firstOrFail();

            $product = Product::query()->findOrFail($data->productId);

            $signedQty = $data->direction === 'in'
                ? $data->quantity
                : bcmul($data->quantity, '-1', 3);

            $after = bcadd((string) $balance->quantity, $signedQty, 3);

            if ($data->direction === 'out'
                && bccomp($after, '0', 3) < 0
                && ! $product->allow_negative_stock) {
                throw new NegativeStockException(
                    "{$product->code} için stok yetersiz."
                );
            }

            $cost = ProductCost::query()->firstOrCreate(
                ['product_id' => $product->id],
                ['moving_average' => '0.0000']
            );

            if ($data->direction === 'in' && $data->updatesAverage) {
                $unitCost = $data->unitCost ?? '0.0000';
                $newAvg = $this->updateAverage->handle(
                    $product->id,
                    $data->quantity,
                    $unitCost
                );
            } else {
                $unitCost = (string) $cost->moving_average;
                $newAvg = (string) $cost->moving_average;
            }

            $balance->quantity = $after;
            $balance->save();

            $totalCost = bcmul($data->quantity, $unitCost, 4);

            $movement = StockMovement::query()->create([
                'product_id' => $data->productId,
                'location_id' => $data->locationId,
                'movement_date' => $data->movementDate,
                'direction' => $data->direction,
                'reason' => $data->reason,
                'quantity' => $data->quantity,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'balance_after' => $after,
                'avg_cost_after' => $newAvg,
                'document_type' => $data->documentType,
                'document_id' => $data->documentId,
                'document_no' => $data->documentNo,
                'note' => $data->note,
                'created_by' => $data->actorUserId,
                'created_by_name' => $data->actorUserName,
            ]);

            // post-write verify: hareket sonrası bakiye ile saklanan değer eşleşmeli
            if (bccomp((string) $movement->balance_after, (string) $balance->quantity, 3) !== 0) {
                throw new DomainException('Stok yazma sonrası bakiye doğrulaması başarısız.');
            }

            return $movement;
        }, attempts: 3);
    }
}
```

## Kurallar

- `quantity` her zaman pozitiftir ve temel birimdedir.
- Yön `direction` alanındadır.
- Çıkışta moving average değişmez.
- Transfer girişinde kaynak çıkış maliyeti kullanılır ve `updatesAverage=false`.
- Negatif stok yalnız `products.allow_negative_stock` izin veriyorsa mümkündür.
- Rezervasyon ayrı alandır; negatif stok izni negatif rezervasyon izni değildir.
- Master user'a cross-DB FK kurulmaz; actor ID + isim snapshot yazılır.
- Para/maliyet hesabında PHP float veya `+`, `-`, `*`, `/` ile decimal aritmetik yapılmaz.

## Kabul ölçütü

- Giriş bakiye artırıyor ve gerekli durumda moving average güncelleniyor.
- Çıkış bakiye azaltıyor, moving average değişmiyor.
- Negatif stok izinsiz ürün hata veriyor.
- Eşzamanlı çıkışlarda bakiye tutarlı kalıyor.
- `total_cost` BCMath ile doğru.
- Actor ID + isim snapshot yazılıyor.
- Post-write verify uyuşmazlıkta transaction rollback ediyor.
- Testler gerçek PostgreSQL'de çalışıyor.

## İstem

> RecordStockMovement ve StockMovementData'yı bu görevdeki sözleşmeyle uygula. Decimal aritmetikte PHP float veya normal matematik operatörü kullanma; BCMath/string kullan. Period DB, lockForUpdate, attempts:3, actor snapshot ve post-write verify kurallarını atlama.
