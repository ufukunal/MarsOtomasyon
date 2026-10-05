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
    /** @param array<string, mixed> $payload */
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
        $widthMm = $profile?->width_mm ?: ($payload['width_mm'] ?? null);
        $heightMm = $profile?->height_mm ?: ($payload['height_mm'] ?? null);
        $paperCode = $profile?->paper_code ?: ($payload['paper_code'] ?? null);

        if ($widthMm && $heightMm) {
            $browser->paperSize(
                (float) $widthMm,
                (float) $heightMm,
                'mm',
            );
        } else {
            $browser->format($paperCode ?: 'A4');
        }

        return new PrintResult(
            content: $browser->pdf(),
            mimeType: 'application/pdf',
            filename: (string) ($payload['filename'] ?? $type->value.'.pdf'),
        );
    }
}
