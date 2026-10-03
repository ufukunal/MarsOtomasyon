<?php

namespace App\Actions\Catalog;

use App\Models\Period\Brand;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Period\PeriodContext;

final class SaveBrand
{
    /** @param array<string, mixed> $data */
    public function handle(array $data, ?Brand $brand = null, ?int $expectedVersion = null): Brand
    {
        MutationAuthorizer::authorize($brand ? 'brands.update' : 'brands.create');
        PeriodContext::ensureWritable();

        $attributes = [
            'name' => trim((string) $data['name']),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        return $brand
            ? $brand->updateWithVersion($attributes, $expectedVersion ?? (int) $brand->version)
            : Brand::query()->create($attributes);
    }
}
