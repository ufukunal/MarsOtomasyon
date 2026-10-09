<?php

use App\Models\Period\ProductionRecipe;

it('v2 production recipe revisions cannot silently overwrite product or bill of material identity', function () {
    $this->createCompanyWithPeriod('V2RECIMM');
    $finishedProduct = $this->createTestProduct(['code' => 'V2-FINISHED-IMM']);
    $recipe = ProductionRecipe::query()->create([
        'product_id' => $finishedProduct->id,
        'number' => 'V2-RECIPE-01',
        'revision_no' => 1,
        'output_quantity' => '10.000',
        'is_active' => true,
    ]);

    expect(fn () => $recipe->update(['output_quantity' => '25.000']))
        ->toThrow(LogicException::class);
    expect((string) $recipe->fresh()->output_quantity)->toBe('10.000');
});

it('v2 production recipe physical deletion is forbidden even when inactive', function () {
    $this->createCompanyWithPeriod('V2RECDEL');
    $product = $this->createTestProduct(['code' => 'V2-FINISHED-DEL']);
    $recipe = ProductionRecipe::query()->create([
        'product_id' => $product->id,
        'number' => 'V2-RECIPE-02',
        'revision_no' => 1,
        'output_quantity' => '1.000',
        'is_active' => false,
    ]);

    expect(fn () => $recipe->delete())->toThrow(LogicException::class);
    expect(ProductionRecipe::query()->whereKey($recipe->id)->exists())->toBeTrue();
});
