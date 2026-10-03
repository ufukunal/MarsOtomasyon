<?php

use App\Exceptions\StaleRecordException;
use App\Models\Period\Product;
use App\Models\Period\Unit;
use Illuminate\Support\Facades\DB;

it('optimistic update version search index ve audit lifecycleını birlikte korur', function () {
    [$company, $period] = $this->createCompanyWithPeriod('OPT');
    $user = $this->createUserWithPeriodAccess($company, $period, 'Yönetici');
    $this->loginToPeriod($user, $company, $period);

    $unit = Unit::query()->where('code', 'ADET')->firstOrFail();

    $product = Product::query()->create([
        'code' => 'OPT-1',
        'name' => 'Eski İsim',
        'unit_id' => $unit->id,
        'vat_rate' => '20',
        'list_price' => '100',
        'currency' => 'TRY',
        'kind' => 'normal',
        'allow_negative_stock' => false,
        'min_stock' => '0',
        'channel_stock_mode' => 'stock',
        'is_active' => true,
    ]);

    $version = (int) $product->version;
    $beforeAudit = DB::connection('period')->table('activity_log')
        ->where('subject_type', Product::class)
        ->where('subject_id', $product->id)
        ->count();

    $updated = $product->updateWithVersion(['name' => 'Yeni İsim'], $version);

    expect($updated->version)->toBe($version + 1)
        ->and($updated->search_index)->toContain('yeni isim')
        ->and(
            DB::connection('period')->table('activity_log')
                ->where('subject_type', Product::class)
                ->where('subject_id', $product->id)
                ->count()
        )->toBeGreaterThan($beforeAudit);

    expect(fn () => $product->updateWithVersion(['name' => 'Bayat Yazma'], $version))
        ->toThrow(StaleRecordException::class);
});
