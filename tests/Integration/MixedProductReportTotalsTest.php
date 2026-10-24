<?php

use App\Support\Reporting\Queries\ProductionOrderReportQuery;
use App\Support\Reporting\Queries\QuarantineReportQuery;
use App\Support\Reporting\ReportExecutionContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\IsolatedPostgres;

it('never adds physical quantities from unrelated production products or quarantine units', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $db = DB::connection('period');
        $products = [
            IsolatedPostgres::productAndLocation(),
            IsolatedPostgres::productAndLocation(),
        ];
        $code = Str::random(10);

        foreach ($products as $index => $item) {
            $qty = $index === 0 ? 10 : 30;
            $recipeId = $db->table('production_recipes')->insertGetId([
                'number' => $code.'-R-'.$index,
                'product_id' => $item['product'],
                'revision_no' => 1, 'output_quantity' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $db->table('production_orders')->insert([
                'number' => $code.'-O-'.$index,
                'document_date' => '2026-05-10',
                'product_id' => $item['product'],
                'recipe_id' => $recipeId,
                'recipe_revision_no' => 1,
                'planned_quantity' => $qty,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $db->table('quarantine_entries')->insert([
                'product_id' => $item['product'],
                'location_id' => $item['location'],
                'quantity' => $qty,
                'unit_cost' => 2,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $context = static function (object $query, array $filters = [], bool $canViewCost = true): ReportExecutionContext {
            $definition = $query->definition();

            return new ReportExecutionContext(
                filters: $filters,
                columns: $definition->defaultColumnKeys($canViewCost),
                sort: $definition->defaultSort,
                totalKeys: array_map(fn ($total): string => $total->key, $definition->totals),
                limit: 50,
                offset: 0,
                canViewCost: $canViewCost,
            );
        };

        $production = new ProductionOrderReportQuery;
        $manyProduction = $production->execute($context($production));
        expect($manyProduction->totalRows)->toBe(2)
            ->and($manyProduction->totals)->toBeEmpty();

        $singleProduction = $production->execute($context($production, [
            'product_id' => $products[0]['product'],
        ]));
        expect((float) $singleProduction->totals['planned_quantity'])->toBe(10.0);

        $quarantine = new QuarantineReportQuery;
        $manyQuarantine = $quarantine->execute($context($quarantine));
        expect($manyQuarantine->totalRows)->toBe(2)
            ->and($manyQuarantine->totals)->not->toHaveKey('quantity')
            ->and($manyQuarantine->totals)->not->toHaveKey('pending_quantity')
            ->and((float) $manyQuarantine->totals['pending_value'])->toBe(80.0);

        $singleQuarantine = $quarantine->execute($context($quarantine, [
            'product_id' => $products[0]['product'],
        ]));
        expect((float) $singleQuarantine->totals['quantity'])->toBe(10.0)
            ->and((float) $singleQuarantine->totals['pending_quantity'])->toBe(10.0);
    });
});
