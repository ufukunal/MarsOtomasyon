<?php

use Tests\ManualAudit\Support\AuditSource;

test('QLT-231 namespace ile app dosya yolu uyuşur', function () {
    $mismatches = [];

    foreach (AuditSource::files(['app']) as $path) {
        $source = AuditSource::read($path);

        if (! preg_match('/namespace\s+(App\\\\[^;]+);/', $source, $match)) {
            continue;
        }

        $directory = dirname($path);
        $expected = str_replace('/', '\\', preg_replace('#^app/?#', 'App\\', $directory));

        if ($match[1] !== $expected) {
            $mismatches[] = "{$path}: {$match[1]} != {$expected}";
        }
    }

    expect($mismatches)->toBe([]);
});

test('QLT-232 class adı ile dosya adı uyuşur', function () {
    $mismatches = [];

    foreach (AuditSource::files(['app']) as $path) {
        $source = AuditSource::read($path);

        if (! preg_match('/\b(?:final\s+|abstract\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/', $source, $match)) {
            continue;
        }

        $expected = pathinfo($path, PATHINFO_FILENAME);

        if ($match[1] !== $expected) {
            $mismatches[] = "{$path}: {$match[1]} != {$expected}";
        }
    }

    expect($mismatches)->toBe([]);
});

test('QLT-233 duplicate namespace class kombinasyonu bulunmaz', function () {
    $seen = [];
    $duplicates = [];

    foreach (AuditSource::files(['app']) as $path) {
        $source = AuditSource::read($path);

        if (! preg_match('/namespace\s+([^;]+);/', $source, $ns)
            || ! preg_match('/\b(?:final\s+|abstract\s+)?class\s+([A-Za-z_][A-Za-z0-9_]*)/', $source, $class)) {
            continue;
        }

        $key = trim($ns[1]).'\\'.$class[1];

        if (isset($seen[$key])) {
            $duplicates[] = "{$key}: {$seen[$key]} / {$path}";
        }

        $seen[$key] = $path;
    }

    expect($duplicates)->toBe([]);
});

test('QLT-234 boş Throwable catch bloğu bulunmaz', function () {
    expect(AuditSource::grep(
        '/catch\s*\(\s*(?:\\\\?Throwable|\\\\?Exception)[^)]*\)\s*\{\s*\}/s',
        ['app'],
    ))->toBe([]);
});

test('QLT-235 kritik exception sessiz swallow edilmez', function () {
    expect(AuditSource::grep(
        '/catch\s*\([^)]*\)\s*\{\s*(?:\/\/[^\n]*\n\s*)?(?:return\s+(?:null|false);)?\s*\}/s',
        ['app/Actions', 'app/Support'],
    ))->toBe([]);
});

test('QLT-236 controllers kritik business mutationı doğrudan DB ile yapmaz', function () {
    expect(AuditSource::grep(
        '/\b(?:insert|update|delete)\s*\(/',
        ['app/Http/Controllers'],
    ))->toBe([]);
});

test('QLT-237 Livewire içinde açık DB transaction başlatılmaz', function () {
    expect(AuditSource::grep(
        '/DB::(?:connection\([^)]*\)->)?transaction\s*\(/',
        ['app/Livewire'],
    ))->toBe([]);
});

test('QLT-238 production request kodunda sleep veya usleep kullanılmaz', function () {
    expect(AuditSource::grep(
        '/\b(?:sleep|usleep)\s*\(/',
        ['app/Http', 'app/Livewire', 'app/Actions'],
    ))->toBe([]);
});

test('QLT-239 Artisan call web ve Livewire katmanında kullanılmaz', function () {
    expect(AuditSource::grep(
        '/\bArtisan::call\s*\(/',
        ['app/Http', 'app/Livewire'],
    ))->toBe([]);
});

test('QLT-240 forceFill unguard withoutEvents kritik actionlarda kullanılmaz', function () {
    expect(AuditSource::grep(
        '/(?:->forceFill\s*\(|Model::unguard\s*\(|::withoutEvents\s*\()/',
        ['app/Actions'],
    ))->toBe([]);
});

test('QLT-241 deprecated Laravel helper kalıntıları bulunmaz', function () {
    expect(AuditSource::grep(
        '/\b(?:str_slug|array_get|array_set|starts_with|ends_with)\s*\(/',
        ['app'],
    ))->toBe([]);
});

test('QLT-242 lifecycle status alanına kontrolsüz request all yazılmaz', function () {
    expect(AuditSource::grep(
        '/(?:create|update|fill)\(\s*\$request->all\(\)\s*\)/',
        ['app'],
    ))->toBe([]);
});

test('QLT-243 money ve cost alanlarında açık float cast kullanılmaz', function () {
    expect(AuditSource::grep(
        '/\(float\)\s*\$[^;\n]*(?:amount|price|cost|total|balance|rate)|floatval\([^)]*(?:amount|price|cost|total|balance|rate)/i',
        ['app/Actions', 'app/Support'],
    ))->toBe([]);
});
