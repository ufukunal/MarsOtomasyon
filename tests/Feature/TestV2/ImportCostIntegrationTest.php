<?php

use App\Models\Period\Contact;
use App\Models\Period\ImportContainer;
use App\Models\Period\ImportCostAllocation;
use App\Models\Period\ImportCostItem;
use App\Models\Period\ImportFile;
use App\Models\Period\ImportPackage;
use App\Support\Imports\ImportCostAllocator;

it('v2 import costs allocate residual rounding exactly once and remain repeatable', function () {
    $this->createCompanyWithPeriod('V2IMPORT');
    $supplier = Contact::query()->create(['title' => 'V2 Supplier', 'type' => 'legal']);
    $firstProduct = $this->createTestProduct(['code' => 'V2-IMP-A']);
    $secondProduct = $this->createTestProduct(['code' => 'V2-IMP-B']);

    $file = ImportFile::query()->create([
        'number' => 'V2-IMP-1',
        'supplier_contact_id' => $supplier->id,
        'currency' => 'USD',
        'exchange_rate' => '10.000000',
        'status' => 'draft',
    ]);
    $container = ImportContainer::query()->create([
        'import_file_id' => $file->id,
        'container_no' => 'V2-CONTAINER',
        'status' => 'planned',
    ]);

    foreach ([[$firstProduct->id, '1.0000', 'A'], [$secondProduct->id, '2.0000', 'B']] as [$productId, $price, $suffix]) {
        ImportPackage::query()->create([
            'import_file_id' => $file->id,
            'container_id' => $container->id,
            'carton_no' => 'V2-'.$suffix,
            'product_id' => $productId,
            'quantity' => '1.000',
            'unit_price' => $price,
            'status' => 'matched',
        ]);
    }
    $cost = ImportCostItem::query()->create([
        'import_file_id' => $file->id,
        'name' => 'Rounding residual',
        'amount' => '0.0001',
        'currency' => 'TRY',
        'exchange_rate' => '1.000000',
        'allocation_basis' => 'value',
    ]);

    $allocator = app(ImportCostAllocator::class);
    $allocator->recalculate($file);

    $shares = ImportCostAllocation::query()
        ->where('import_cost_item_id', $cost->id)
        ->orderBy('package_id')
        ->pluck('allocated_amount_try')
        ->all();

    expect($shares)->toBe(['0.0000', '0.0001'])
        ->and(ImportPackage::query()->orderBy('id')->pluck('goods_value_try')->all())
        ->toBe(['10.0000', '20.0000'])
        ->and(ImportPackage::query()->orderBy('id')->pluck('landed_unit_cost_try')->all())
        ->toBe(['10.0000', '20.0001']);

    $allocator->recalculate($file);
    expect(ImportCostAllocation::query()->where('import_cost_item_id', $cost->id)->count())->toBe(2)
        ->and(ImportCostAllocation::query()->where('import_cost_item_id', $cost->id)->sum('allocated_amount_try'))
        ->toBe('0.0001');
});

it('v2 import costing refuses an import file with no matched packages', function () {
    $this->createCompanyWithPeriod('V2IMPEMPTY');
    $supplier = Contact::query()->create(['title' => 'V2 Empty Supplier', 'type' => 'legal']);
    $file = ImportFile::query()->create([
        'number' => 'V2-EMPTY', 'supplier_contact_id' => $supplier->id,
        'currency' => 'USD', 'exchange_rate' => '1.000000', 'status' => 'draft',
    ]);

    expect(fn () => app(ImportCostAllocator::class)->recalculate($file))
        ->toThrow(DomainException::class, 'eşleşmiş en az bir koli');
});
