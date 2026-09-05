<?php

use App\Modules\Core\Models\Company;
use App\Modules\Imports\Migration\LegacyMigrationControl;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;

uses(DatabaseMigrations::class);

it('keeps stable legacy identity from rehearsal through cutover checkpoints', function (): void {
    $company = Company::query()->create([
        'code' => 'M24-MIG',
        'name' => 'M24 Migration Company',
        'status' => 'active',
        'base_currency_code' => 'TRY',
        'timezone' => 'Europe/Istanbul',
    ]);
    $companyId = (int) $company->getKey();
    $control = app(LegacyMigrationControl::class);
    $sourceId = $control->registerSource(
        $companyId,
        'legacy-erp',
        'Legacy ERP',
        hash('sha256', 'legacy-erp-schema-v1'),
    );

    $dryRunId = $control->stageRecord(
        $companyId,
        $sourceId,
        'account',
        'customer:42',
        ['name' => 'Acme', 'balance' => ['currency' => 'TRY', 'value' => '125.50']],
        true,
    );
    $liveId = $control->stageRecord(
        $companyId,
        $sourceId,
        'account',
        'customer:42',
        ['balance' => ['value' => '125.50', 'currency' => 'TRY'], 'name' => 'Acme'],
        false,
    );

    expect($liveId)->toBe($dryRunId);
    expect((bool) DB::table('migration_source_records')->where('id', $liveId)->value('dry_run'))->toBeFalse();

    $control->markImported($companyId, $liveId, 'account', 9001);
    $control->recordReconciliation($companyId, $sourceId, 'opening-balance', 'balance', '125.50', '125.50', true);
    $control->recordChannelCheckpoint($companyId, $sourceId, 'trendyol', 'merchant-123', true, false, 'cursor-9', '2026-09-01T10:00:00Z', 'evt-88');
    $control->markRehearsed($companyId, $sourceId);

    expect($control->readyForCutover($companyId, $sourceId))->toBeFalse();

    $control->recordChannelCheckpoint($companyId, $sourceId, 'trendyol', 'merchant-123', true, true, 'cursor-9', '2026-09-01T10:00:00Z', 'evt-88');
    expect($control->readyForCutover($companyId, $sourceId))->toBeTrue();

    $control->beginCutover($companyId, $sourceId);
    $control->completeCutover($companyId, $sourceId);

    expect(DB::table('migration_sources')->where('id', $sourceId)->value('status'))->toBe('completed');
});

it('rejects payload drift for an already known legacy source identity', function (): void {
    $company = Company::query()->create([
        'code' => 'M24-DRIFT',
        'name' => 'M24 Drift Company',
        'status' => 'active',
        'base_currency_code' => 'TRY',
        'timezone' => 'Europe/Istanbul',
    ]);
    $companyId = (int) $company->getKey();
    $control = app(LegacyMigrationControl::class);
    $sourceId = $control->registerSource($companyId, 'legacy-a', 'Legacy A', hash('sha256', 'schema-a'));
    $control->stageRecord($companyId, $sourceId, 'product', 'sku:ABC', ['name' => 'First'], true);

    expect(fn () => $control->stageRecord($companyId, $sourceId, 'product', 'sku:ABC', ['name' => 'Changed'], false))
        ->toThrow(DomainException::class, 'payload drift');
});
