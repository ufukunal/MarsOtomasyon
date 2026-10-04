<?php

use App\Actions\Stock\RecordStockMovement;
use App\DataObjects\StockMovementData;
use App\Models\Period\Location;
use App\Models\Period\ProductCost;
use App\Models\Period\StockBalance;
use App\Models\Period\StockMovement;
use App\Support\Period\PeriodContext;
use Illuminate\Support\Facades\DB;

function faz2ConcurrencyWarehouse(string $code): Location
{
    return Location::query()->create([
        'code' => $code,
        'name' => $code.' Depo',
        'kind' => 'warehouse',
        'is_default' => false,
        'is_active' => true,
    ]);
}

/**
 * @param  Closure(int):void  $callback
 * @return list<string>
 */
function faz2RunParallel(
    int $companyId,
    int $periodId,
    int $workers,
    Closure $callback,
): array {
    if (! function_exists('pcntl_fork')) {
        throw new RuntimeException('Faz 2 concurrency testleri için pcntl_fork zorunludur.');
    }

    DB::disconnect('period');
    DB::disconnect('master');

    $children = [];
    $errorFiles = [];

    for ($index = 0; $index < $workers; $index++) {
        $errorFile = sys_get_temp_dir().'/mars-faz2-concurrency-'.getmypid()."-{$index}.log";
        @unlink($errorFile);

        $pid = pcntl_fork();

        if ($pid === -1) {
            throw new RuntimeException('Concurrency child process oluşturulamadı.');
        }

        if ($pid === 0) {
            try {
                DB::purge('period');
                DB::purge('master');
                PeriodContext::clear();
                PeriodContext::useSystem($companyId, $periodId);
                $callback($index);
                exit(0);
            } catch (Throwable $exception) {
                file_put_contents(
                    $errorFile,
                    $exception::class.': '.$exception->getMessage(),
                );
                exit(1);
            }
        }

        $children[] = $pid;
        $errorFiles[] = $errorFile;
    }

    $errors = [];

    foreach ($children as $index => $pid) {
        pcntl_waitpid($pid, $status);

        if (! pcntl_wifexited($status) || pcntl_wexitstatus($status) !== 0) {
            $errors[] = is_file($errorFiles[$index])
                ? (string) file_get_contents($errorFiles[$index])
                : "Child {$pid} başarısız.";
        }

        @unlink($errorFiles[$index]);
    }

    DB::purge('period');
    DB::purge('master');
    PeriodContext::clear();
    PeriodContext::useSystem($companyId, $periodId);

    return $errors;
}

it('ilk iki eşzamanlı giriş tek balance ve tek cost satırı üretir', function () {
    [$company, $period] = $this->createCompanyWithPeriod('RACEFIRST');
    $product = $this->createTestProduct(['code' => 'RACE-1']);
    $location = faz2ConcurrencyWarehouse('RACE-A');

    $productId = $product->id;
    $locationId = $location->id;

    $errors = faz2RunParallel(
        $company->id,
        $period->id,
        2,
        function () use ($productId, $locationId): void {
            app(RecordStockMovement::class)->handle(new StockMovementData(
                productId: $productId,
                locationId: $locationId,
                movementDate: '2026-10-01',
                direction: 'in',
                reason: 'purchase',
                quantity: '1.000',
                unitCost: '100.0000',
                updatesAverage: true,
            ));
        },
    );

    expect($errors)->toBe([])
        ->and(StockBalance::query()->where('product_id', $productId)->where('location_id', $locationId)->count())->toBe(1)
        ->and(ProductCost::query()->where('product_id', $productId)->count())->toBe(1)
        ->and((string) StockBalance::query()->where('product_id', $productId)->where('location_id', $locationId)->value('quantity'))->toBe('2.000')
        ->and((string) ProductCost::query()->where('product_id', $productId)->value('moving_average'))->toBe('100.0000')
        ->and(StockMovement::query()->where('product_id', $productId)->count())->toBe(2);
});

it('10 paralel çıkış sonunda bakiye ve hareket sayısı deterministik kalır', function () {
    [$company, $period] = $this->createCompanyWithPeriod('RACEOUT');
    $product = $this->createTestProduct(['code' => 'RACE-OUT']);
    $location = faz2ConcurrencyWarehouse('RACE-B');

    app(RecordStockMovement::class)->handle(new StockMovementData(
        productId: $product->id,
        locationId: $location->id,
        movementDate: '2026-10-01',
        direction: 'in',
        reason: 'purchase',
        quantity: '10.000',
        unitCost: '25.0000',
        updatesAverage: true,
    ));

    $productId = $product->id;
    $locationId = $location->id;

    $errors = faz2RunParallel(
        $company->id,
        $period->id,
        10,
        function (int $index) use ($productId, $locationId): void {
            app(RecordStockMovement::class)->handle(new StockMovementData(
                productId: $productId,
                locationId: $locationId,
                movementDate: '2026-10-02',
                direction: 'out',
                reason: 'sale',
                quantity: '1.000',
                documentType: 'concurrency_test',
                documentId: $index + 1,
            ));
        },
    );

    expect($errors)->toBe([])
        ->and((string) StockBalance::query()->where('product_id', $productId)->where('location_id', $locationId)->value('quantity'))->toBe('0.000')
        ->and(StockMovement::query()->where('product_id', $productId)->count())->toBe(11)
        ->and((string) ProductCost::query()->where('product_id', $productId)->value('moving_average'))->toBe('25.0000');
});
