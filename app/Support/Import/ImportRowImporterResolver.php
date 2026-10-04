<?php

namespace App\Support\Import;

use App\Actions\Import\ContactRowImporter;
use App\Actions\Import\OpeningStockRowImporter;
use App\Actions\Import\PriceListRowImporter;
use App\Actions\Import\ProductRowImporter;
use RuntimeException;

final class ImportRowImporterResolver
{
    public function resolve(string $type): object
    {
        return match ($type) {
            'contact' => app(ContactRowImporter::class),
            'product' => app(ProductRowImporter::class),
            'price_list' => app(PriceListRowImporter::class),
            'opening_stock' => app(OpeningStockRowImporter::class),
            default => throw new RuntimeException('Desteklenmeyen import tipi.'),
        };
    }
}
