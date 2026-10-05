<?php

namespace App\Support\Printing\Drivers;

use App\Enums\PrintType;
use App\Models\PrintProfile;
use App\Support\Printing\PrintDriver;
use App\Support\Printing\PrintResult;
use InvalidArgumentException;

final class ZplDriver implements PrintDriver
{
    public function send(
        PrintType $type,
        array $payload,
        ?PrintProfile $profile,
    ): PrintResult {
        $zpl = $payload['zpl'] ?? null;

        if (! is_string($zpl) || $zpl === '') {
            throw new InvalidArgumentException('ZplDriver payload zpl alanı zorunludur.');
        }

        if (! str_starts_with($zpl, '^XA') || ! str_ends_with($zpl, '^XZ')) {
            throw new InvalidArgumentException('ZPL çıktı ^XA ile başlayıp ^XZ ile bitmelidir.');
        }

        return new PrintResult(
            content: $zpl,
            mimeType: 'application/zpl',
            filename: (string) ($payload['filename'] ?? $type->value.'.zpl'),
        );
    }
}
