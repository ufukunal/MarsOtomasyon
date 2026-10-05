<?php

namespace App\Actions\Production;

use App\Actions\Numbering\GenerateDocumentNumber;
use App\Actions\Periods\EnsurePeriodOpen;
use App\Models\Period\ProductionOrder;
use App\Models\Period\ProductionOrderComponent;
use App\Models\Period\ProductionRecipe;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ConfirmProductionOrder
{
    public function __construct(
        private readonly EnsurePeriodOpen $ensurePeriodOpen,
        private readonly GenerateDocumentNumber $numbers,
    ) {}

    public function handle(ProductionOrder $order, string $idempotencyKey): ProductionOrder
    {
        MutationAuthorizer::authorize('production_orders.update');

        $id = IdempotencyKey::run(
            $idempotencyKey,
            'production-order.confirm:'.$order->id,
            fn (): int => DB::connection('period')->transaction(function () use ($order): int {
                $locked = ProductionOrder::query()->lockForUpdate()->findOrFail($order->id);

                if ($locked->status !== 'draft') {
                    throw new DomainException('Yalnız taslak üretim emri onaylanabilir.');
                }

                $date = CarbonImmutable::parse((string) $locked->document_date);
                $this->ensurePeriodOpen->handle($date);

                $recipe = ProductionRecipe::query()
                    ->with('lines')
                    ->lockForUpdate()
                    ->findOrFail((int) $locked->recipe_id);

                if ((int) $recipe->product_id !== (int) $locked->product_id || $recipe->lines->isEmpty()) {
                    throw new DomainException('Üretim emri reçete snapshotı için geçerli reçete bulunamadı.');
                }

                ProductionOrderComponent::query()
                    ->where('production_order_id', $locked->id)
                    ->delete();

                foreach ($recipe->lines as $line) {
                    $plannedQuantity = bcadd(
                        bcdiv(
                            bcmul((string) $line->quantity, (string) $locked->planned_quantity, 8),
                            (string) $recipe->output_quantity,
                            8,
                        ),
                        '0',
                        3,
                    );
                    $plannedBaseQuantity = bcadd(
                        bcdiv(
                            bcmul((string) $line->base_quantity, (string) $locked->planned_quantity, 8),
                            (string) $recipe->output_quantity,
                            8,
                        ),
                        '0',
                        3,
                    );

                    if (bccomp($plannedQuantity, '0', 3) <= 0
                        || bccomp($plannedBaseQuantity, '0', 3) <= 0) {
                        throw new DomainException('Üretim emri component snapshot miktarı sıfıra yuvarlanamaz.');
                    }

                    ProductionOrderComponent::query()->create([
                        'production_order_id' => $locked->id,
                        'component_product_id' => $line->component_product_id,
                        'unit_id' => $line->unit_id,
                        'planned_quantity' => $plannedQuantity,
                        'planned_base_quantity' => $plannedBaseQuantity,
                        'conversion_factor' => $line->conversion_factor,
                    ]);
                }

                $actor = auth()->user();
                $locked->number = $locked->number ?: $this->numbers->handle('production_order', $date->year);
                $locked->recipe_revision_no = $recipe->revision_no;
                $locked->status = 'confirmed';
                $locked->confirmed_by = $actor?->id;
                $locked->confirmed_by_name = $actor?->name;
                $locked->version = (int) $locked->version + 1;
                $locked->save();

                AuditContext::period(
                    'Üretim emri onaylandı ve reçete snapshotı donduruldu.',
                    [
                        'production_order_id' => $locked->id,
                        'number' => $locked->number,
                        'recipe_id' => $recipe->id,
                        'recipe_revision_no' => $recipe->revision_no,
                    ],
                    $locked,
                    'production_order_confirmed',
                );

                return (int) $locked->id;
            }, attempts: 3),
        );

        return ProductionOrder::query()
            ->with(['product', 'recipe', 'components.componentProduct'])
            ->findOrFail((int) $id);
    }
}
