<?php

use App\Support\Integrity\IntegrityResult;

it('counts mismatches without treating checked rows as failures', function (): void {
    $empty = new IntegrityResult(checked: 500, mismatches: [], durationMs: 12);
    $bad = new IntegrityResult(checked: 500, mismatches: [['id' => 2], ['id' => 8]], durationMs: 13);
    expect($empty->mismatchCount())->toBe(0)
        ->and($bad->mismatchCount())->toBe(2)
        ->and($bad->checked)->toBe(500);
});
