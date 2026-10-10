<?php

use App\Support\DocumentTemplates\TemplateExpressionValidator;
use App\Support\DocumentTemplates\TemplateTokenRegistry;

it('accepts registered tokens and rejects code execution in templates', function (): void {
    $validator = new TemplateExpressionValidator(new TemplateTokenRegistry);
    $validator->validateText('{{ company.name }} - {{ document.number }}');
    expect(true)->toBeTrue();
    foreach (['{{ user.password }}', '{{ company.name|raw }}', '<?php echo 1;', '@php echo 1;', '{!! $unsafe !!}', '{{ unclosed'] as $bad) {
        expect(fn () => $validator->validateText($bad))->toThrow(DomainException::class);
    }
});

it('validates nested definitions recursively', function (): void {
    $validator = new TemplateExpressionValidator(new TemplateTokenRegistry);
    $validator->validateDefinition(['sections' => [['title' => '{{ product.code }}']]]);
    expect(fn () => $validator->validateDefinition(['sections' => [['text' => '{{ product.private }}']]]))
        ->toThrow(DomainException::class);
});
