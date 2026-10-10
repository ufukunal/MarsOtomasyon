<?php

use App\Models\IntegrityReport;
use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use App\Support\Integrity\IntegrityRunner;
use Tests\Support\IsolatedPostgres;

it('saves check count, mismatches, metadata and execution time to the isolated period database', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $check = Mockery::mock(IntegrityCheck::class);
        $check->shouldReceive('name')->andReturn('v4-integrity-demo');
        $check->shouldReceive('run')->once()->andReturn(new IntegrityResult(
            checked: 2,
            mismatches: [['id' => 1, 'difference' => '4.000']],
            durationMs: 17,
            meta: ['source' => 'local_fixture'],
        ));

        $result = app(IntegrityRunner::class)->run($check, persist: true);

        expect($result->mismatchCount())->toBe(1);

        $saved = IntegrityReport::query()->where('check_name', 'v4-integrity-demo')->firstOrFail();
        expect((int) $saved->checked_count)->toBe(2)
            ->and((int) $saved->mismatch_count)->toBe(1)
            ->and($saved->details['mismatches'][0]['id'])->toBe(1);
    });
});
