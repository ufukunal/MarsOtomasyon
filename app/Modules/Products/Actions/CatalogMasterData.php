<?php

namespace App\Modules\Products\Actions;

final readonly class CatalogMasterData
{
    public function __construct(
        public string $code,
        public string $name,
        public bool $isActive = true,
    ) {}
}
