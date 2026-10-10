<?php

use App\Support\Operations\RecoverySetArchive;
use Illuminate\Support\Str;

it('selects only a matching SQL dump from a disposable recovery directory', function (): void {
    $directory = sys_get_temp_dir().'/mars-v4-restore-'.Str::random(12);
    mkdir($directory, 0700, true);
    $dump = $directory.'/mars_test_master.sql';

    try {
        file_put_contents($dump, "SELECT 1;\n");

        expect((new RecoverySetArchive)->findSqlDump($directory, 'mars_test_master'))
            ->toBe($dump);
        expect(fn () => (new RecoverySetArchive)->findSqlDump($directory, 'mars_test_other'))
            ->toThrow(RuntimeException::class);
    } finally {
        if (is_file($dump)) {
            unlink($dump);
        }

        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
});
