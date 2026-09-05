<?php

namespace App\Modules\Products\Labels;

use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

final class LabelTemplateEngine
{
    /** @param array<string, mixed> $payload */
    public function render(string $template, array $payload): string
    {
        $flat = Arr::dot($payload);

        $rendered = preg_replace_callback('/\{\{\s*([A-Za-z0-9_.]+)\s*\}\}/', function (array $matches) use ($flat): string {
            $key = $matches[1];
            if (! array_key_exists($key, $flat)) {
                throw ValidationException::withMessages([
                    'body' => "Unknown label token: {$key}",
                ]);
            }

            $value = $flat[$key];
            if ($value === null) {
                return '';
            }
            if (is_bool($value)) {
                return $value ? '1' : '0';
            }
            if (! is_scalar($value)) {
                throw ValidationException::withMessages([
                    'body' => "Label token is not scalar: {$key}",
                ]);
            }

            return (string) $value;
        }, $template);

        if ($rendered === null) {
            throw ValidationException::withMessages(['body' => 'Label template could not be rendered.']);
        }

        return $rendered;
    }
}
