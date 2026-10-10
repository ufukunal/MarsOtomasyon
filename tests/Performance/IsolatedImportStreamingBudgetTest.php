<?php

use App\Support\Import\ImportFileReader;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Assert;

it('keeps CSV preview bounded when input holds thousands of rows', function (): void {
    if (getenv('MARS_PERFORMANCE_TESTS_APPROVED') !== 'I_APPROVE_LOCAL_BENCHMARK') {
        Assert::markTestSkipped('Separate local performance authorization required.');
    }

    $disk = 'mars_v4_streaming_performance';
    Storage::fake($disk);
    $csv = "code;quantity\n";

    for ($i = 0; $i < 4000; $i++) {
        $csv .= 'SKU-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT).";1.000\n";
    }

    Storage::disk($disk)->put('rows.csv', $csv);

    $started = hrtime(true);
    $preview = (new ImportFileReader)->previewRows($disk, 'rows.csv', 'rows.csv', 20);
    $ms = (hrtime(true) - $started) / 1_000_000;

    expect($preview)->toHaveCount(20)
        ->and($preview[0]['code'])->toBe('SKU-000000')
        ->and($ms)->toBeLessThan(15000);
});
