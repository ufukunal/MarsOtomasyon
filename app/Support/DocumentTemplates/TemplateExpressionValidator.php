<?php

namespace App\Support\DocumentTemplates;

use DomainException;

final class TemplateExpressionValidator
{
    public function __construct(private readonly TemplateTokenRegistry $tokens) {}

    public function validateText(string $text): void
    {
        if (preg_match('/<\?(?:php|=)?|\{!!|!!\}|@(?:php|endphp|foreach|for|while|if|include|extends|section|yield)\b/i', $text)) {
            throw new DomainException('PHP/Blade expression template içinde kullanılamaz.');
        }

        preg_match_all('/\{\{(.*?)\}\}/s', $text, $matches);

        foreach ($matches[1] ?? [] as $expression) {
            $token = trim((string) $expression);

            if (! preg_match('/^[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*$/D', $token)) {
                throw new DomainException("Arbitrary template expression reddedildi: {$token}.");
            }

            if (! $this->tokens->allows($token)) {
                throw new DomainException("Bilinmeyen template token: {$token}.");
            }
        }

        $withoutTokens = preg_replace('/\{\{.*?\}\}/s', '', $text) ?? $text;

        if (str_contains($withoutTokens, '{{') || str_contains($withoutTokens, '}}')) {
            throw new DomainException('Bozuk template token sözdizimi.');
        }
    }

    /** @param array<string,mixed> $definition */
    public function validateDefinition(array $definition): void
    {
        $this->walk($definition);
    }

    private function walk(mixed $value): void
    {
        if (is_string($value)) {
            $this->validateText($value);

            return;
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($value as $item) {
            $this->walk($item);
        }
    }
}
