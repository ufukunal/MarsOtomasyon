<?php

use Tests\ManualAudit\Support\AuditSource;

test('FILE-211 upload validator yalnız configte izinli MIME tiplerini kabul eder', function () {
    expect(AuditSource::read('app/Support/Security/SecureUploadValidator.php'))
        ->toContain('attachments.mime_extensions')
        ->toContain('Dosya türüne izin verilmiyor.');
});

test('FILE-212 extension MIME mismatch reddedilir', function () {
    expect(AuditSource::read('app/Support/Security/SecureUploadValidator.php'))
        ->toContain("! in_array(\$extension, \$allowed, true)")
        ->toContain('Dosya uzantısı içerik türüyle uyumlu değil.');
});

test('FILE-213 double extension ve SVG upload reddedilir', function () {
    $source = AuditSource::read('app/Support/Security/SecureUploadValidator.php');

    expect($source)
        ->toContain("substr_count(\$originalName, '.') > 1")
        ->toContain("\$extension === 'svg'");
});

test('FILE-214 CSV export spreadsheet formula injectionı prefix ile neutralize eder', function () {
    $source = AuditSource::read('app/Support/Reporting/Export/CsvReportExporter.php');

    expect($source)
        ->toContain('[=+\\\\-@]')
        ->toContain('return "\'".$text;');
});

test('FILE-215 XLSX import error raporu kullanıcı değerlerini explicit string yazar', function () {
    $source = AuditSource::read('app/Http/Controllers/ImportErrorReportController.php');

    expect($source)
        ->toContain('setValueExplicit')
        ->toContain('DataType::TYPE_STRING');
});

test('FILE-216 spreadsheet formula prefixleri text veri olarak ele alınır', function () {
    expect(AuditSource::read('app/Support/Reporting/Export/CsvReportExporter.php'))
        ->toMatch('/\[=\+\\\\-@\]/');
});

test('FILE-217 upload boyutu config max size ile sınırlandırılır', function () {
    expect(AuditSource::read('app/Support/Security/SecureUploadValidator.php'))
        ->toContain("config('attachments.max_size')");
});

test('FILE-218 import reader unsupported extensionı kontrollü hata ile reddeder', function () {
    expect(AuditSource::read('app/Support/Import/ImportFileReader.php'))
        ->toContain("default => throw new RuntimeException('Desteklenmeyen içe aktarma dosya türü.')");
});

test('FILE-219 import error report permission ve aktif period fiziksel scope ile korunur', function () {
    $source = AuditSource::read('app/Http/Controllers/ImportErrorReportController.php');

    expect($source)
        ->toContain("can('imports.view')")
        ->toContain('CardImportBatch::query()');
});
