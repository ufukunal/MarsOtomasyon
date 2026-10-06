<?php

namespace App\Support\DocumentTemplates;

use DomainException;

final class SafeTemplateRenderer
{
    public function __construct(
        private readonly TemplateTokenRegistry $tokens,
        private readonly TemplateExpressionValidator $validator,
    ) {}

    public function render(
        string $text,
        FrozenDocumentRenderData $data,
        string $renderType = 'html_pdf',
    ): string {
        $this->validator->validateText($text);

        if (! in_array($renderType, ['html_pdf', 'text', 'zpl'], true)) {
            throw new DomainException('Güvenli renderer render_type desteklemiyor.');
        }

        $source = $renderType === 'html_pdf'
            ? e($text)
            : $this->plainText($text);

        $rendered = preg_replace_callback(
            '/\{\{\s*([a-z][a-z0-9_]*\.[a-z][a-z0-9_]*)\s*\}\}/',
            function (array $matches) use ($data, $renderType): string {
                $token = $matches[1];

                if (! $this->tokens->allows($token)) {
                    throw new DomainException("Bilinmeyen template token: {$token}.");
                }

                return $this->escapeValue($data->value($token), $renderType);
            },
            $source,
        ) ?? '';

        return $renderType === 'zpl'
            ? str_replace(['^', '~'], '', $rendered)
            : $rendered;
    }

    private function escapeValue(string $value, string $renderType): string
    {
        return match ($renderType) {
            'html_pdf' => e($value),
            'text' => $this->plainText($value),
            'zpl' => str_replace(['^', '~'], '', $this->plainText($value)),
            default => throw new DomainException('Güvenli renderer render_type desteklemiyor.'),
        };
    }

    private function plainText(string $value): string
    {
        $value = str_replace(["\0", "\r"], ['', ''], $value);

        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    }
}
