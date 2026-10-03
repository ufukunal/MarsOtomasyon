<?php

namespace App\Support\Printing\Drivers;

use App\Enums\PrintType;
use App\Models\PrintProfile;
use App\Support\Printing\PrintDriver;
use App\Support\Printing\PrintResult;
use InvalidArgumentException;
use Spatie\Browsershot\Browsershot;

class BrowserDriver implements PrintDriver
{
    public function send(
        PrintType $type,
        array $payload,
        ?PrintProfile $profile,
    ): PrintResult {
        $html = $payload['html'] ?? null;

        if (! is_string($html) || $html === '') {
            throw new InvalidArgumentException('BrowserDriver payload html alanı zorunludur.');
        }

        $browser = Browsershot::html($html);

        if ($profile?->width_mm && $profile?->height_mm) {
            $browser->paperSize(
                (float) $profile->width_mm,
                (float) $profile->height_mm,
                'mm',
            );
        } else {
            $browser->format($profile?->paper_code ?: 'A4');
        }

        return new PrintResult(
            content: $browser->pdf(),
            mimeType: 'application/pdf',
            filename: (string) ($payload['filename'] ?? $type->value.'.pdf'),
        );
    }
}
