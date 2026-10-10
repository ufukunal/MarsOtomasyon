<?php

use App\Actions\Printing\RunBatchPrint;
use App\Support\Printing\PrintResult;

it('counts print successes, hashes finished output and isolates failed print jobs', function (): void {
    $done = new PrintResult('Label OK', 'text/plain', 'label.txt');
    $batch = (new RunBatchPrint)->execute([
        fn () => $done,
        fn () => throw new RuntimeException('password=do-not-log'),
        fn () => new PrintResult('Invoice', 'application/pdf', 'invoice.pdf'),
        fn () => 'not a print result',
    ]);

    expect($batch['done'])->toBe(2)
        ->and($batch['failed'])->toBe(2)
        ->and($batch['results'])->toHaveCount(4)
        ->and($batch['results'][0]['output_hash'])->toBe(hash('sha256', 'Label OK'))
        ->and($batch['results'][2]['filename'])->toBe('invoice.pdf')
        ->and($batch['results'][1]['error_summary'])->toBe('Print operation failed.')
        ->and(json_encode($batch))->not->toContain('do-not-log');
});

it('refuses non-callable batch entries rather than silently treating them as failures', function (): void {
    expect(fn () => (new RunBatchPrint)->execute(['invalid']))
        ->toThrow(InvalidArgumentException::class);
});

it('returns empty results without attempting any printer connection', function (): void {
    expect((new RunBatchPrint)->execute([]))->toBe([
        'done' => 0,
        'failed' => 0,
        'results' => [],
    ]);
});
