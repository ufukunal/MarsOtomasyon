<?php

namespace App\Enums;

/** The print mode changes only presentation, never persisted amounts. */
enum BusinessDocumentPrintVariant: string
{
    case PlainPriced = 'plain_priced';
    case ImagePriced = 'image_priced';
    case PlainUnpriced = 'plain_unpriced';
    case ImageUnpriced = 'image_unpriced';

    public function showImages(): bool
    {
        return in_array($this, [self::ImagePriced, self::ImageUnpriced], true);
    }

    public function showPrices(): bool
    {
        return in_array($this, [self::PlainPriced, self::ImagePriced], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::PlainPriced => 'Düz Fiyatlı',
            self::ImagePriced => 'Resimli Fiyatlı',
            self::PlainUnpriced => 'Fiyatsız',
            self::ImageUnpriced => 'Resimli Fiyatsız',
        };
    }
}
