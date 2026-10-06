<?php

namespace App\Support\Integrity\Checks;

use App\Models\Period\ImportCostAllocation;
use App\Models\Period\ImportFile;
use App\Models\Period\ProductCost;
use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use App\Support\Period\PeriodContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ImportIntegrityCheck implements IntegrityCheck
{
    public function name(): string
    {
        return 'imports_phase7';
    }

    public function run(): IntegrityResult
    {
        $started = hrtime(true);

        if (! Schema::connection('period')->hasTable('import_files')) {
            return new IntegrityResult(
                checked: 0,
                mismatches: [],
                durationMs: $this->elapsed($started),
                meta: ['status' => 'not_applicable'],
            );
        }

        $mismatches = [];
        $provenanceFiles = ImportFile::query()
            ->whereNotNull('source_period_id')
            ->orWhereNotNull('source_import_file_id')
            ->orWhereNotNull('source_number')
            ->get();

        foreach ($provenanceFiles as $file) {
            if ($file->source_period_id === null
                || $file->source_import_file_id === null
                || trim((string) $file->source_number) === ''
                || (int) $file->source_period_id === (int) PeriodContext::periodId()) {
                $mismatches[] = [
                    'import_file_id' => $file->id,
                    'reason' => 'carry_provenance_invalid',
                ];
            }
        }

        $files = ImportFile::query()
            ->whereIn('status', ['received', 'closed'])
            ->with(['packages.container', 'costItems'])
            ->get();

        $latestClosedByProduct = [];

        foreach ($files->where('status', 'closed') as $closedFile) {
            foreach ($closedFile->packages->whereNotNull('product_id') as $package) {
                $productId = (int) $package->product_id;
                $known = $latestClosedByProduct[$productId] ?? null;

                $closedAt = $closedFile->closed_at?->getTimestamp() ?? 0;

                if ($known === null
                    || $closedAt > $known['closed_at']
                    || ($closedAt === $known['closed_at'] && (int) $closedFile->id > $known['file_id'])) {
                    $latestClosedByProduct[$productId] = [
                        'file_id' => (int) $closedFile->id,
                        'closed_at' => $closedAt,
                    ];
                }
            }
        }

        foreach ($files as $file) {
            if ($file->exchange_rate_locked_at === null
                || $file->exchange_rate_date === null
                || bccomp((string) ($file->exchange_rate ?? '0'), '0', 6) <= 0) {
                $mismatches[] = ['import_file_id' => $file->id, 'reason' => 'exchange_rate_not_locked'];
            }

            $exchangeRateDate = $file->exchange_rate_date;
            $receivedAt = $file->received_at;

            if ($receivedAt === null
                || $exchangeRateDate === null
                || $exchangeRateDate->toDateString() !== $receivedAt->toDateString()
                || (int) $exchangeRateDate->year !== (int) PeriodContext::year()) {
                $mismatches[] = ['import_file_id' => $file->id, 'reason' => 'exchange_rate_date_mismatch'];
            }

            foreach ($file->costItems as $item) {
                $allocated = '0.0000';
                $allocations = ImportCostAllocation::query()
                    ->with('package')
                    ->where('import_cost_item_id', $item->id)
                    ->orderBy('id')
                    ->get();

                foreach ($allocations as $allocation) {
                    $allocated = bcadd($allocated, (string) $allocation->allocated_amount_try, 4);
                    $package = $allocation->package;

                    if ($package === null
                        || (int) $package->import_file_id !== (int) $file->id
                        || (int) $allocation->product_id !== (int) $package->product_id
                        || (int) $allocation->container_id !== (int) $package->container_id) {
                        $mismatches[] = [
                            'allocation_id' => $allocation->id,
                            'reason' => 'allocation_source_mismatch',
                        ];
                    }
                }

                if ($item->amount_try === null || bccomp($allocated, (string) $item->amount_try, 4) !== 0) {
                    $mismatches[] = ['cost_item_id' => $item->id, 'reason' => 'allocation_total_mismatch'];
                }
            }

            foreach ($file->packages as $package) {
                if ($package->product_id === null
                    || $package->location_id === null
                    || $package->goods_value_try === null
                    || $package->allocated_cost_try === null
                    || $package->landed_unit_cost_try === null
                    || bccomp((string) $package->quantity, '0', 3) <= 0
                    || $package->container === null
                    || (int) $package->container->import_file_id !== (int) $file->id
                    || $package->status !== 'received') {
                    $mismatches[] = ['package_id' => $package->id, 'reason' => 'received_package_incomplete'];

                    continue;
                }

                $allocated = bcadd((string) ImportCostAllocation::query()
                    ->where('package_id', $package->id)
                    ->sum('allocated_amount_try'), '0', 4);
                $expectedUnit = bcdiv(
                    bcadd((string) $package->goods_value_try, $allocated, 4),
                    (string) $package->quantity,
                    4,
                );

                if (bccomp((string) $package->allocated_cost_try, $allocated, 4) !== 0
                    || bccomp((string) $package->landed_unit_cost_try, $expectedUnit, 4) !== 0) {
                    $mismatches[] = ['package_id' => $package->id, 'reason' => 'landed_cost_mismatch'];
                }
            }

            $expected = [];
            foreach ($file->packages as $package) {
                if ($package->product_id === null || $package->location_id === null) {
                    continue;
                }

                $key = $package->product_id.':'.$package->location_id;
                $expected[$key]['quantity'] = bcadd(
                    $expected[$key]['quantity'] ?? '0.000',
                    (string) $package->quantity,
                    3,
                );
                $expected[$key]['value'] = bcadd(
                    $expected[$key]['value'] ?? '0.0000',
                    bcmul((string) $package->quantity, (string) $package->landed_unit_cost_try, 8),
                    4,
                );
            }

            $movementQuery = DB::connection('period')->table('stock_movements')
                ->where('document_type', 'import_file')
                ->where('document_id', $file->id)
                ->where('direction', 'in');

            $sourceRows = (clone $movementQuery)->get(['reason', 'document_no']);

            if ($sourceRows->count() !== $file->packages->count()
                || $sourceRows->contains(fn ($row) => (string) $row->reason !== 'import'
                    || (string) $row->document_no !== (string) $file->number)) {
                $mismatches[] = [
                    'import_file_id' => $file->id,
                    'reason' => 'stock_receipt_source_mismatch',
                ];
            }

            $actual = (clone $movementQuery)
                ->selectRaw('product_id, location_id, SUM(quantity)::text AS quantity, SUM(total_cost)::text AS value')
                ->groupBy('product_id', 'location_id')
                ->get();

            foreach ($actual as $row) {
                $key = $row->product_id.':'.$row->location_id;
                $expectedRow = $expected[$key] ?? null;

                if (! $expectedRow
                    || bccomp($expectedRow['quantity'], (string) $row->quantity, 3) !== 0
                    || bccomp($expectedRow['value'], (string) $row->value, 4) !== 0) {
                    $mismatches[] = ['import_file_id' => $file->id, 'key' => $key, 'reason' => 'stock_receipt_mismatch'];
                }

                unset($expected[$key]);
            }

            foreach (array_keys($expected) as $key) {
                $mismatches[] = ['import_file_id' => $file->id, 'key' => $key, 'reason' => 'stock_receipt_missing'];
            }

            if ($file->status === 'closed') {
                foreach ($file->packages->whereNotNull('product_id')->groupBy('product_id') as $productId => $packages) {
                    if (($latestClosedByProduct[(int) $productId]['file_id'] ?? null) !== (int) $file->id) {
                        continue;
                    }

                    $qty = '0.000';
                    $value = '0.0000';

                    foreach ($packages as $package) {
                        $qty = bcadd($qty, (string) $package->quantity, 3);
                        $value = bcadd(
                            $value,
                            bcmul((string) $package->quantity, (string) $package->landed_unit_cost_try, 8),
                            4,
                        );
                    }

                    if (bccomp($qty, '0', 3) <= 0) {
                        $mismatches[] = [
                            'import_file_id' => $file->id,
                            'product_id' => (int) $productId,
                            'reason' => 'product_import_quantity_invalid',
                        ];

                        continue;
                    }

                    $expectedImportCost = bcdiv($value, $qty, 4);
                    $storedImportCost = ProductCost::query()
                        ->where('product_id', (int) $productId)
                        ->value('import_cost');

                    if ($storedImportCost === null
                        || bccomp((string) $storedImportCost, $expectedImportCost, 4) !== 0) {
                        $mismatches[] = [
                            'import_file_id' => $file->id,
                            'product_id' => (int) $productId,
                            'reason' => 'product_import_cost_mismatch',
                        ];
                    }
                }
            }
        }

        return new IntegrityResult(
            checked: $files->count() + $provenanceFiles->whereNotIn('status', ['received', 'closed'])->count(),
            mismatches: $mismatches,
            durationMs: $this->elapsed($started),
        );
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
