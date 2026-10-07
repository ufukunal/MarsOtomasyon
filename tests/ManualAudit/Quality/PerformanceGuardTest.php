<?php

use Tests\ManualAudit\Support\AuditSource;

test('PERF-244 report query sınıflarında Model all çağrısı yoktur', function () {
    expect(AuditSource::grep(
        '/::all\s*\(\s*\)/',
        ['app/Support/Reporting/Queries'],
    ))->toBe([]);
});

test('PERF-245 export kodunda çıplak unbounded get çağrısı yoktur', function () {
    expect(AuditSource::grep(
        '/->get\s*\(\s*\)\s*;/',
        ['app/Support/Reporting/Export', 'app/Actions/Reporting'],
    ))->toBe([]);
});

test('PERF-246 import okuyucu tüm dosyayı file_get_contents ile memorye almaz', function () {
    expect(AuditSource::grep(
        '/file_get_contents\s*\(/',
        ['app/Support/Import', 'app/Actions/Import', 'app/Actions/Imports'],
    ))->toBe([]);
});

test('PERF-247 period carry toplu tabloları Model all ile yüklemez', function () {
    expect(AuditSource::grep(
        '/::all\s*\(\s*\)/',
        ['app/Actions/Periods', 'app/Support/PeriodCarry'],
    ))->toBe([]);
});

test('PERF-248 obvious query inside foreach kritik rapor ve carry kodunda bulunmaz', function () {
    $offenders = AuditSource::grep(
        '/foreach\s*\([^)]*\)\s*\{(?:(?!\n\}).){0,800}(?:::query\(\)|DB::connection\()[\s\S]{0,250}?->(?:first|find|get|exists|count)\s*\(/',
        ['app/Support/Reporting', 'app/Actions/Periods'],
    );

    expect($offenders)->toBe([]);
});

test('PERF-249 marketplace HTTP çağrıları listing koleksiyon döngüsüne gömülmez', function () {
    $offenders = AuditSource::grep(
        '/foreach\s*\([^)]*\)\s*\{(?:(?!\n\}).){0,1000}->(?:get|post|put|delete|send)\s*\(/s',
        ['app/Actions/Channels', 'app/Jobs'],
    );

    expect($offenders)->toBe([]);
});

test('PERF-250 reporting query katmanı period reconnect işlemi yapmaz', function () {
    expect(AuditSource::grep(
        '/DB::(?:purge|reconnect)\s*\(/',
        ['app/Support/Reporting/Queries'],
    ))->toBe([]);
});
