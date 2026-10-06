<?php

use Tests\ManualAudit\Support\AuditSource;

test('manual audit paketi planlanan 273 kontrol kimliğinin tamamını içerir', function () {
    $ids = [];

    foreach (AuditSource::files(['tests/ManualAudit']) as $path) {
        if (str_ends_with($path, '/CoverageManifestTest.php')) {
            continue;
        }

        preg_match_all(
            '/(?:CI|MP|TX|RACE|IDEM|LOCK|PAR|NUM|SEC|AUTH|HTTP|TPL|MASS|CH|FILE|LOG|QLT|PERF|DEAD|CFG|REG)-(\d{3})/',
            AuditSource::read($path),
            $matches,
        );

        foreach ($matches[1] as $id) {
            $ids[] = (int) $id;
        }
    }

    sort($ids);
    $ids = array_values(array_unique($ids));

    expect($ids)->toBe(range(1, 273));
});
