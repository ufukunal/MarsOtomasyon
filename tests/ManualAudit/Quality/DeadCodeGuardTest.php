<?php

use Tests\ManualAudit\Support\AuditSource;

test('DEAD-251 channel adapter sınıf adları benzersizdir', function () {
    $seen = [];
    $duplicates = [];

    foreach (AuditSource::files(['app/Support/Channels']) as $path) {
        $source = AuditSource::read($path);

        if (! preg_match('/class\s+([A-Za-z0-9_]+Adapter)\b/', $source, $match)) {
            continue;
        }

        if (isset($seen[$match[1]])) {
            $duplicates[] = [$match[1], $seen[$match[1]], $path];
        }

        $seen[$match[1]] = $path;
    }

    expect($duplicates)->toBe([]);
});

test('DEAD-252 action sınıflarında Legacy Old Deprecated isimli duplicate implementation yoktur', function () {
    expect(AuditSource::grep(
        '/class\s+(?:Legacy|Old|Deprecated)[A-Za-z0-9_]+/',
        ['app/Actions'],
    ))->toBe([]);
});

test('DEAD-253 route isimleri duplicate değildir', function () {
    $source = AuditSource::read('routes/web.php');
    preg_match_all('/->name\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $source, $matches);
    $counts = array_count_values($matches[1]);
    $duplicates = array_keys(array_filter($counts, static fn (int $count): bool => $count > 1));

    expect($duplicates)->toBe([]);
});

test('DEAD-254 Livewire class dosyaları boş component değildir', function () {
    $offenders = [];

    foreach (AuditSource::files(['app/Livewire']) as $path) {
        $source = AuditSource::read($path);

        if (preg_match('/class\s+\w+[^{]*\{\s*\}/s', $source)) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});

test('DEAD-255 Blade viewlerde olmayan component namespace importu bulunmaz', function () {
    expect(AuditSource::grep(
        '/@livewire\(\s*[\'"]\s*[\'"]/',
        ['resources/views'],
    ))->toBe([]);
});

test('DEAD-256 integrity checker dosyalarının hepsi runner tarafından keşfedilebilir sözleşmeye sahiptir', function () {
    $offenders = [];

    foreach (AuditSource::files(['app/Support/Integrity/Checks']) as $path) {
        if (! str_contains(AuditSource::read($path), 'implements IntegrityCheck')) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});

test('DEAD-257 console command sınıflarında duplicate signature bulunmaz', function () {
    $seen = [];
    $duplicates = [];

    foreach (AuditSource::files(['app/Console', 'app/Support/Integrity']) as $path) {
        $source = AuditSource::read($path);

        if (! preg_match('/\$signature\s*=\s*[\'"]([^\'"]+)/', $source, $match)) {
            continue;
        }

        if (isset($seen[$match[1]])) {
            $duplicates[] = [$match[1], $seen[$match[1]], $path];
        }

        $seen[$match[1]] = $path;
    }

    expect($duplicates)->toBe([]);
});

test('DEAD-258 migration dosyalarında duplicate migration basename bulunmaz', function () {
    $seen = [];
    $duplicates = [];

    foreach (AuditSource::files(['database/migrations/master', 'database/migrations/period']) as $path) {
        $base = basename($path);

        if (isset($seen[$base])) {
            $duplicates[] = [$base, $seen[$base], $path];
        }

        $seen[$base] = $path;
    }

    expect($duplicates)->toBe([]);
});

test('DEAD-259 kritik actionlarda copy paste edilmiş ikinci sınıf tanımı bulunmaz', function () {
    $offenders = [];

    foreach (AuditSource::files(['app/Actions']) as $path) {
        if (preg_match_all('/\bclass\s+[A-Za-z_][A-Za-z0-9_]*/', AuditSource::read($path)) > 1) {
            $offenders[] = $path;
        }
    }

    expect($offenders)->toBe([]);
});
