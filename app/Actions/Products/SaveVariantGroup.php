<?php

namespace App\Actions\Products;

use App\Models\Period\VariantGroup;
use App\Support\Auth\MutationAuthorizer;

final class SaveVariantGroup
{
    public function handle(array $data, ?VariantGroup $group = null, ?int $expectedVersion = null): VariantGroup
    {
        MutationAuthorizer::authorize($group ? 'variant_groups.update' : 'variant_groups.create');

        $attributes = [
            'name' => trim((string) $data['name']),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        return $group
            ? $group->updateWithVersion($attributes, $expectedVersion ?? (int) $group->version)
            : VariantGroup::query()->create($attributes);
    }
}
