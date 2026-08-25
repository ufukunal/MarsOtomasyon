<?php

namespace App\Modules\Products\Enums;

enum ProductFileKind: string
{
    case Technical = 'technical';
    case Media = 'media';

    public function label(): string
    {
        return match ($this) {
            self::Technical => 'Teknik Dosya',
            self::Media => 'Medya',
        };
    }
}
