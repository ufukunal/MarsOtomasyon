<?php

use Tests\ManualAudit\Support\AuditSource;

test('TPL-179 HTML template source ve token değerleri escape edilir', function () {
    $source = AuditSource::read('app/Support/DocumentTemplates/SafeTemplateRenderer.php');

    expect($source)
        ->toContain('? e($text)')
        ->toContain("'html_pdf' => e(\$value)");
});

test('TPL-180 script payload rendererda raw HTML olarak bırakılmaz', function () {
    expect(AuditSource::read('app/Support/DocumentTemplates/SafeTemplateRenderer.php'))
        ->toContain('e($text)');
});

test('TPL-181 event handler payloadları HTML escaping ile etkisizleşir', function () {
    expect(AuditSource::read('app/Support/DocumentTemplates/SafeTemplateRenderer.php'))
        ->toContain("'html_pdf' => e(\$value)");
});

test('TPL-182 token whitelist dışı ifade reddedilir', function () {
    expect(AuditSource::read('app/Support/DocumentTemplates/TemplateExpressionValidator.php'))
        ->toContain('if (! $this->tokens->allows($token))')
        ->toContain('Bilinmeyen template token');
});

test('TPL-183 PHP Blade arbitrary expressionları reddedilir', function () {
    $source = AuditSource::read('app/Support/DocumentTemplates/TemplateExpressionValidator.php');

    expect($source)
        ->toContain('PHP/Blade expression template içinde kullanılamaz.')
        ->toContain('Arbitrary template expression reddedildi');
});

test('TPL-184 safe renderer Blade raw echo çalıştırmaz', function () {
    expect(AuditSource::read('app/Support/DocumentTemplates/SafeTemplateRenderer.php'))
        ->not->toContain('Blade::render')
        ->not->toContain('eval(');
});

test('TPL-185 ZPL active command injection karakterleri temizlenir', function () {
    expect(AuditSource::read('app/Support/DocumentTemplates/SafeTemplateRenderer.php'))
        ->toContain("str_replace(['^', '~'], '',");
});

test('TPL-186 document sourced text render sırasında escape edilir', function () {
    $source = AuditSource::read('app/Support/DocumentTemplates/SafeTemplateRenderer.php');

    expect($source)->toContain('escapeValue($data->value($token), $renderType)');
});
