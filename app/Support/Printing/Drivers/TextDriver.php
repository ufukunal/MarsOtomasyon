<?php

namespace App\Support\Printing\Drivers;

use App\Enums\PrintType;
use App\Models\PrintProfile;
use App\Support\Printing\PrintDriver;
use App\Support\Printing\PrintResult;
use InvalidArgumentException;

final class TextDriver implements PrintDriver
{
    public function send(
        PrintType $type,
        array $payload,
        ?PrintProfile $profile,
    ): PrintResult {
        $text = $payload['text'] ?? null;

        if (! is_string($text)) {
            throw new InvalidArgumentException('TextDriver payload text alanı zorunludur.');
        }

        return new PrintResult(
            content: $text,
            mimeType: 'text/plain; charset=UTF-8',
            filename: (string) ($payload['filename'] ?? $type->value.'.txt'),
        );
    }
}
