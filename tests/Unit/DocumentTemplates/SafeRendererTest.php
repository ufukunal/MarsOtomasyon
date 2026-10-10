<?php

use App\Support\DocumentTemplates\FrozenDocumentRenderData;
use App\Support\DocumentTemplates\SafeTemplateRenderer;
use App\Support\DocumentTemplates\TemplateExpressionValidator;
use App\Support\DocumentTemplates\TemplateTokenRegistry;

it('HTML-escapes untrusted template data and resolves only approved tokens', function (): void {
    $tokens = new TemplateTokenRegistry;
    $renderer = new SafeTemplateRenderer($tokens, new TemplateExpressionValidator($tokens));
    $data = new FrozenDocumentRenderData(['company' => ['name' => '<script>alert(1)</script>']]);
    $html = $renderer->render('Customer: {{ company.name }}', $data);
    expect($html)->toContain('&lt;script&gt;')->not->toContain('<script>');
});

it('does not emit injected raw ZPL commands from business values', function (): void {
    $tokens = new TemplateTokenRegistry;
    $renderer = new SafeTemplateRenderer($tokens, new TemplateExpressionValidator($tokens));
    $data = new FrozenDocumentRenderData(['product' => ['name' => 'Widget^XA~JS']]);
    expect($renderer->render('{{ product.name }}', $data, 'zpl'))->toBe('WidgetXAJS');
});

it('rejects unknown rendering modes and array-valued template fields', function (): void {
    $tokens = new TemplateTokenRegistry;
    $renderer = new SafeTemplateRenderer($tokens, new TemplateExpressionValidator($tokens));
    $data = new FrozenDocumentRenderData(['document' => ['number' => 'INV-1']]);
    expect(fn () => $renderer->render('{{ document.number }}', $data, 'php'))->toThrow(DomainException::class);
    expect(fn () => new FrozenDocumentRenderData(['document' => ['number' => ['bad']]]))->toThrow(DomainException::class);
});
