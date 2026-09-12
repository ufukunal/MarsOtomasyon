<?php

use App\Modules\UpdateCenter\UpdatePackageStager;
use Illuminate\Filesystem\Filesystem;
use ReflectionMethod;
use RuntimeException;
use ZipArchive;

it('extracts regular files without allowing archive paths to escape staging', function (): void {
    config()->set('update-center.max_files', 100);
    config()->set('update-center.max_extracted_bytes', 1024 * 1024);

    $root = sys_get_temp_dir().'/mars-update-archive-'.bin2hex(random_bytes(6));
    $zipPath = $root.'/release.zip';
    $releasePath = $root.'/release';
    mkdir($root, 0700, true);

    $zip = new ZipArchive();
    expect($zip->open($zipPath, ZipArchive::CREATE))->toBeTrue();
    $zip->addFromString('artisan', '#!/usr/bin/env php');
    $zip->addFromString('composer.json', '{}');
    $zip->addFromString('Dockerfile.production', 'FROM scratch');
    $zip->addFromString('docker-compose.production.yml', "services: {}\n");
    $zip->close();

    $method = new ReflectionMethod(UpdatePackageStager::class, 'extractVerifiedZip');
    [$count, $bytes] = $method->invoke(app(UpdatePackageStager::class), $zipPath, $releasePath);

    expect($count)->toBe(4)
        ->and($bytes)->toBeGreaterThan(0)
        ->and(is_file($releasePath.'/artisan'))->toBeTrue();

    app(Filesystem::class)->deleteDirectory($root);
});

it('rejects zip slip traversal before an escaping file is written', function (): void {
    config()->set('update-center.max_files', 100);
    config()->set('update-center.max_extracted_bytes', 1024 * 1024);

    $root = sys_get_temp_dir().'/mars-update-traversal-'.bin2hex(random_bytes(6));
    $zipPath = $root.'/release.zip';
    $releasePath = $root.'/release';
    $escapePath = $root.'/escape.php';
    mkdir($root, 0700, true);

    $zip = new ZipArchive();
    expect($zip->open($zipPath, ZipArchive::CREATE))->toBeTrue();
    $zip->addFromString('../escape.php', '<?php echo "bad";');
    $zip->close();

    $method = new ReflectionMethod(UpdatePackageStager::class, 'extractVerifiedZip');

    expect(fn () => $method->invoke(app(UpdatePackageStager::class), $zipPath, $releasePath))
        ->toThrow(RuntimeException::class, 'path traversal')
        ->and(is_file($escapePath))->toBeFalse();

    app(Filesystem::class)->deleteDirectory($root);
});
