<?php

use App\Models\BackupRun;
use App\Support\Operations\RecoverySetArchive;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

it('extracts only a verified local ZIP recovery fixture and locates the SQL dump', function (): void {
    $disk = 'mars_v4_backup_fixture';
    Storage::fake($disk);
    $workspace = sys_get_temp_dir().'/mars-v4-recovery-'.Str::random(12);
    $zipFile = tempnam(sys_get_temp_dir(), 'mars-zip-');

    if ($zipFile === false) {
        throw new RuntimeException('Cannot create temporary ZIP fixture.');
    }

    try {
        $zip = new ZipArchive;
        expect($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
        $zip->addFromString('mars_test_master.sql', 'SELECT 1;');
        $zip->close();

        Storage::disk($disk)->put('recovery.zip', file_get_contents($zipFile));
        $checksum = Storage::disk($disk)->checksum('recovery.zip');
        $run = new BackupRun([
            'status' => 'done',
            'storage_disk' => $disk,
            'master_backup_path' => 'recovery.zip',
            'checksum_manifest' => [$disk => ['checksum' => $checksum]],
        ]);
        $archive = new RecoverySetArchive;
        $result = $archive->verifyAndExtract($run, $workspace);

        expect($result['checksum'])->toBe($checksum)
            ->and(is_file($archive->findSqlDump($result['directory'], 'mars_test_master')))->toBeTrue();
    } finally {
        File::deleteDirectory($workspace);
        unlink($zipFile);
    }
});

it('rejects a tampered recovery checksum before creating an extraction directory', function (): void {
    $disk = 'mars_v4_backup_tampered';
    Storage::fake($disk);
    Storage::disk($disk)->put('fake.zip', 'not a real ZIP');
    $workspace = sys_get_temp_dir().'/mars-v4-corrupt-'.Str::random(12);
    $run = new BackupRun([
        'status' => 'done',
        'storage_disk' => $disk,
        'master_backup_path' => 'fake.zip',
        'checksum_manifest' => [$disk => ['checksum' => str_repeat('0', 32)]],
    ]);

    expect(fn () => (new RecoverySetArchive)->verifyAndExtract($run, $workspace))
        ->toThrow(RuntimeException::class);
    expect(is_dir($workspace))->toBeFalse();
});
