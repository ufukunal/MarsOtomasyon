<?php

namespace App\Actions\Stock;

use App\Actions\Periods\EnsurePeriodOpen;
use App\DataObjects\OpeningStockRowData;
use App\DataObjects\StockMovementData;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Models\Period\StockMovement;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ImportOpeningStock
{
    private const ADVISORY_LOCK_KEY = 20260301;

    public function __construct(
        private readonly EnsurePeriodOpen $ensurePeriodOpen,
        private readonly RecordStockMovement $recordMovement,
    ) {}

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function handle(
        array $rows,
        string $openingDate,
        string $batchId,
        ?int $actorUserId,
        ?string $actorUserName,
    ): void {
        MutationAuthorizer::authorize('imports.create');

        if ($rows === []) {
            throw new DomainException('Açılış stoğu için uygulanacak satır bulunamadı.');
        }

        $date = CarbonImmutable::parse($openingDate)->startOfDay();

        DB::connection('period')->transaction(function () use (
            $rows,
            $date,
            $batchId,
            $actorUserId,
            $actorUserName,
        ): void {
            DB::connection('period')->select(
                'SELECT pg_advisory_xact_lock(?)',
                [self::ADVISORY_LOCK_KEY],
            );

            $this->ensurePeriodOpen->handle($date);

            if (StockMovement::query()->where('reason', 'opening')->exists()) {
                throw new DomainException('Bu dönem için açılış stoğu daha önce oluşturulmuş.');
            }

            $resolved = [];

            foreach ($rows as $row) {
                $data = OpeningStockRowData::fromArray($row);

                if ($data->productCode === '' || $data->locationCode === '') {
                    throw new DomainException('Açılış satırında ürün ve lokasyon kodu zorunludur.');
                }

                if (! is_numeric($data->quantity) || bccomp($data->quantity, '0', 3) <= 0) {
                    throw new DomainException("{$data->productCode} için açılış miktarı pozitif sayısal olmalıdır.");
                }

                if ($data->unitCost === '' || ! is_numeric($data->unitCost) || bccomp($data->unitCost, '0', 4) < 0) {
                    throw new DomainException("{$data->productCode} için birim maliyet zorunlu ve sıfırdan büyük/eşit olmalıdır.");
                }

                $product = Product::query()
                    ->where('code', $data->productCode)
                    ->firstOrFail();
                $location = Location::query()
                    ->where('code', $data->locationCode)
                    ->firstOrFail();

                $resolved[] = [$product, $location, $data];
            }

            usort(
                $resolved,
                fn (array $left, array $right): int => [$left[0]->id, $left[1]->id]
                    <=> [$right[0]->id, $right[1]->id],
            );

            foreach ($resolved as [$product, $location, $data]) {
                $this->recordMovement->handle(new StockMovementData(
                    productId: $product->id,
                    locationId: $location->id,
                    movementDate: $date->toDateString(),
                    direction: 'in',
                    reason: 'opening',
                    quantity: bcadd($data->quantity, '0', 3),
                    unitCost: bcadd($data->unitCost, '0', 4),
                    updatesAverage: true,
                    documentType: 'opening_import',
                    documentNo: $batchId,
                    actorUserId: $actorUserId,
                    actorUserName: $actorUserName,
                ));
            }

            AuditContext::period(
                'Açılış stok bakiyesi içe aktarıldı.',
                [
                    'batch_id' => $batchId,
                    'opening_date' => $date->toDateString(),
                    'row_count' => count($resolved),
                ],
                null,
                'opening_stock_imported',
            );
        }, attempts: 3);
    }
}
