<?php

namespace App\Actions\Products;

use App\Models\Period\VariantAttribute;
use App\Models\Period\VariantGroup;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Period\PeriodContext;

final class SaveVariantAttribute
{
    /** @param array<string, mixed> $data */
    public function handle(
        VariantGroup $group,
        array $data,
        ?VariantAttribute $attribute = null,
        ?int $expectedVersion = null,
    ): VariantAttribute {
        MutationAuthorizer::authorize('variant_groups.update');
        PeriodContext::ensureWritable();

        abort_if($attribute && (int) $attribute->variant_group_id !== (int) $group->id, 404);

        $attributes = [
            'variant_group_id' => $group->id,
            'name' => trim((string) $data['name']),
            'sort_order' => (int) ($data['sort_order'] ?? $group->attributes()->count()),
        ];

        return $attribute
            ? $attribute->updateWithVersion($attributes, $expectedVersion ?? (int) $attribute->version)
            : VariantAttribute::query()->create($attributes);
    }
}
