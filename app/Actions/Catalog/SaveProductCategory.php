<?php

namespace App\Actions\Catalog;

use App\Models\Period\ProductCategory;
use Illuminate\Validation\ValidationException;

final class SaveProductCategory
{
    public function handle(array $data, ?ProductCategory $category = null, ?int $expectedVersion = null): ProductCategory
    {
        $parentId = $data['parent_id'] ?? null;

        if ($category && $parentId && (int) $category->id === (int) $parentId) {
            throw ValidationException::withMessages(['parent_id' => 'Kategori kendisinin altına taşınamaz.']);
        }

        if ($parentId) {
            $parent = ProductCategory::query()->findOrFail($parentId);
            $depth = 2;
            $cursor = $parent;

            while ($cursor) {
                if ($category && (int) $cursor->id === (int) $category->id) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'Kategori kendi alt dalının altına taşınamaz.',
                    ]);
                }

                if (! $cursor->parent_id) {
                    break;
                }

                $depth++;

                if ($depth > 3) {
                    throw ValidationException::withMessages([
                        'parent_id' => 'Kategori ağacı en fazla 3 seviye olabilir.',
                    ]);
                }

                $cursor = ProductCategory::query()->findOrFail($cursor->parent_id);
            }
        }

        $attributes = [
            'parent_id' => $parentId ?: null,
            'name' => trim((string) $data['name']),
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        return $category
            ? $category->updateWithVersion($attributes, $expectedVersion ?? (int) $category->version)
            : ProductCategory::query()->create($attributes);
    }
}
