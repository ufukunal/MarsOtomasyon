<?php

use App\Actions\Companies\CheckCompanyCopyPermission;
use App\Enums\CompanyCopyPermissionType;
use App\Models\CompanyCopyPermission;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

it('allows only active, directed and matching company copy grants', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $sourceId): void {
        $db = DB::connection('master');
        $targetId = $db->table('companies')->insertGetId([
            'code' => 'v4-target-'.strtolower(\Illuminate\Support\Str::random(8)),
            'name' => 'V4 target company',
            'db_prefix' => 'v4_target',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $kind = CompanyCopyPermissionType::cases()[0];
        $policy = app(CheckCompanyCopyPermission::class);
        expect($policy->handle($sourceId, $targetId, $kind))->toBeFalse();

        $record = CompanyCopyPermission::query()->create([
            'source_company_id' => $sourceId,
            'target_company_id' => $targetId,
            'type' => $kind,
            'is_active' => true,
        ]);

        expect($policy->handle($sourceId, $targetId, $kind))->toBeTrue()
            ->and($policy->handle($targetId, $sourceId, $kind))->toBeFalse();

        $record->is_active = false;
        $record->save();
        expect($policy->handle($sourceId, $targetId, $kind))->toBeFalse();
    });
});

it('rejects a permission edge that copies a company onto itself', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        expect(fn () => CompanyCopyPermission::query()->create([
            'source_company_id' => $companyId,
            'target_company_id' => $companyId,
            'type' => CompanyCopyPermissionType::cases()[0],
            'is_active' => true,
        ]))->toThrow(InvalidArgumentException::class);
    });
});
