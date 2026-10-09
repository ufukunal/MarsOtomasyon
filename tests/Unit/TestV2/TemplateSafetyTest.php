<?php

use App\Support\DocumentTemplates\FrozenDocumentRenderData;
use App\Support\DocumentTemplates\SafeTemplateRenderer;
use App\Support\DocumentTemplates\TemplateExpressionValidator;
use App\Support\DocumentTemplates\TemplateTokenRegistry;

it('v2 templates accept allowlisted identifiers and block unknown or arbitrary expressions', function () {
    $tokens = new TemplateTokenRegistry;
    $validator = new TemplateExpressionValidator($tokens);

    expect($tokens->allows('company.name'))->toBeTrue()
        ->and($tokens->allows('company.password'))->toBeFalse()
        ->and($tokens->allows('user.email'))->toBeFalse();

    $validator->validateText('Belge {{document.number}} / {{company.name}}');

    foreach (['{{company.password}}', '{{document.number.upper()}}', '{{ config.app_key }}', '{{user.__class__}}', '{{document.unknown}}'] as $invalid) {
        expect(fn () => $validator->validateText($invalid))->toThrow(DomainException::class);
    }
});

it('v2 templates reject blade php and malformed token syntax', function (string $payload) {
    $validator = new TemplateExpressionValidator(new TemplateTokenRegistry);
    expect(fn () => $validator->validateText($payload))->toThrow(DomainException::class);
})->with([
    '<?php echo "danger";',
    '<?= "danger" ?>',
    '@foreach($items as $item)',
    '@include("secret")',
    '{!! $raw !!}',
    '{{document.number',
    'document.number}}',
]);

it('v2 templates recursively validate nested definitions', function () {
    $validator = new TemplateExpressionValidator(new TemplateTokenRegistry);
    $validator->validateDefinition(['sections' => [['text' => '{{company.name}}']]]);

    expect(fn () => $validator->validateDefinition(['sections' => [['text' => '{{company.password}}']]]))
        ->toThrow(DomainException::class);
});

it('v2 frozen documents reject non-scalar data and never expose missing tokens', function () {
    expect(fn () => new FrozenDocumentRenderData(['company' => ['name' => ['nested']]]))
        ->toThrow(DomainException::class);

    $data = new FrozenDocumentRenderData(['company' => ['name' => 'Acme', 'verified' => true]]);
    expect($data->value('company.name'))->toBe('Acme')
        ->and($data->value('company.verified'))->toBe('1')
        ->and($data->value('company.missing'))->toBe('');
});

it('v2 templates HTML-escape both customer-controlled fields and static template HTML', function () {
    $tokens = new TemplateTokenRegistry;
    $renderer = new SafeTemplateRenderer($tokens, new TemplateExpressionValidator($tokens));
    $data = new FrozenDocumentRenderData(['company' => ['name' => '<script>alert(1)</script>']]);

    $output = $renderer->render('<b>{{company.name}}</b>', $data);
    expect($output)->toContain('&lt;b&gt;')
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->not->toContain('<script>');
});

it('v2 templates prevent ZPL command injection in interpolated customer data', function () {
    $tokens = new TemplateTokenRegistry;
    $renderer = new SafeTemplateRenderer($tokens, new TemplateExpressionValidator($tokens));
    $data = new FrozenDocumentRenderData(['company' => ['name' => '^XA^FO0,0~DG']]);

    expect($renderer->render('{{company.name}}', $data, 'zpl'))
        ->toBe('XAFO0,0DG');
});

it('v2 templates drop text mode control bytes', function () {
    $tokens = new TemplateTokenRegistry;
    $renderer = new SafeTemplateRenderer($tokens, new TemplateExpressionValidator($tokens));
    $data = new FrozenDocumentRenderData(['company' => ['name' => "A\0B\rC\x01D"]]);

    expect($renderer->render('{{company.name}}', $data, 'text'))->toBe('ABCD');
});

it('v2 templates do not accept arbitrary render output modes', function () {
    $tokens = new TemplateTokenRegistry;
    $renderer = new SafeTemplateRenderer($tokens, new TemplateExpressionValidator($tokens));
    expect(fn () => $renderer->render('plain', new FrozenDocumentRenderData([]), 'shell'))
        ->toThrow(DomainException::class);
});
