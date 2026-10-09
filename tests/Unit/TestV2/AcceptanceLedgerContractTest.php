<?php

it('V2-001 312 acceptance IDs retain unique traceable criteria and priority without silently closing unproven work', function () {
    $path = base_path('docs/testing/TEST_V2_ACCEPTANCE_312.csv');
    expect(is_file($path))->toBeTrue();

    $handle = fopen($path, 'rb');
    expect($handle)->not->toBeFalse();
    try {
        $header = fgetcsv($handle);
        expect($header)->toHaveCount(9);
        $ids = [];
        $rowCount = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null]) {
                continue;
            }
            expect($row)->toHaveCount(9);
            [$id, $area, $priority, $type, $criterion, $status, $testFile, $runId] = $row;
            expect($id)->toMatch('/^V2-\d{3}$/')
                ->and($area)->not->toBe('')
                ->and($priority)->toBeIn(['P0', 'P1', 'P2'])
                ->and($type)->toBeIn(['U', 'F', 'A', 'B', 'S', 'L', 'O'])
                ->and($criterion)->not->toBe('')
                ->and($status)->toBe('OPEN - not individually proven')
                ->and($testFile)->toBe('')
                ->and($runId)->toBe('');
            $ids[] = $id;
            $rowCount++;
        }
        expect($rowCount)->toBe(312)
            ->and(count(array_unique($ids)))->toBe(312)
            ->and($ids)->toBe(array_map(fn (int $i): string => sprintf('V2-%03d', $i), range(1, 312)));
    } finally {
        fclose($handle);
    }
});