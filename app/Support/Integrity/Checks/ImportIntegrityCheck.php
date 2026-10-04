<?php

namespace App\Support\Integrity\Checks;

use App\Models\Period\ImportCostAllocation;
use App\Models\Period\ImportFile;
use App\Models\Period\ImportPackage;
use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
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
        $files = ImportFile::query()
            ->whereIn('status', ['received', 'closed'])
            ->with(['packages', 'costItems'])
            ->get();

        foreach ($files as $file) {
            if ($file->exchange_rate_locked_at === null
                || $file->exchange_rate_date === null
                || bccomp((string) ($file->exchange_rate ?? '0'), '0', 6) <= 0) {
                $mismatches[] = ['import_file_id' => $file->id, 'reason' => 'exchange_rate_not_locked'];
            }

            foreach ($file->costItems as $item) {
                $allocated = bcadd((string) ImportCostAllocation::query()
                    ->where('import_cost_item_id', $item->id)
                    ->sum('allocated_amount_try'), '0', 4);

                if ($item->amount_try === null || bccomp($allocated, (string) $item->amount_try, 4) !== 0) {
                    $mismatches[] = ['cost_item_id' => $item->id, 'reason' => 'allocation_total_mismatch'];
                }
            }

            foreach ($file->packages as $package) {
                if ($package->product_id === null
                    || $package->location_id === null
                    || $package->landed_unit_cost_try === null
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

            $actual = DB::connection('period')->table('stock_movements')
                ->where('document_type', 'import_file')
                ->where('document_id', $file->id)
                ->where('direction', 'in')
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
        }

        return new IntegrityResult(
            checked: $files->count(),
            mismatches: $mismatches,
            durationMs: $this->elapsed($started),
        );
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
