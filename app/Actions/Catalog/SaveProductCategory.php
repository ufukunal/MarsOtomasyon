<?php

namespace App\Actions\Catalog;

use App\Support\Auth\MutationAuthorizer;
use App\Models\Period\ProductCategory;
use Illuminate\Validation\ValidationException;

final class SaveProductCategory
{
    public function handle(array $data, ?ProductCategory $category = null, ?int $expectedVersion = null): ProductCategory
    {
        MutationAuthorizer::authorize($category ? 'product_categories.update' : 'product_categories.create');
        $parentId = $data['parent_id'] ?? null;

        if ($category && $parentId && (int) $category->id === (int) $parentId) {
            throw ValidationException::withMessages(['parent_id' => 'Kategori kendisinin altına taşınamaz.']);
        }

        $newDepth = $this->proposedDepth($parentId ? (int) $parentId : null, $category);

        $subtreeHeight = $category
            ? $this->subtreeHeight($category)
            : 1;

        if (($newDepth + $subtreeHeight - 1) > 3) {
            throw ValidationException::withMessages([
                'parent_id' => 'Kategori ağacı alt kategoriler dahil en fazla 3 seviye olabilir.',
            ]);
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

    private function proposedDepth(?int $parentId, ?ProductCategory $moving): int
    {
        if (! $parentId) {
            return 1;
        }

        $depth = 2;
        $visited = [];
        $cursor = ProductCategory::query()->findOrFail($parentId);

        while ($cursor) {
            if (isset($visited[$cursor->id])) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Kategori ağacında döngü tespit edildi.',
                ]);
            }

            $visited[$cursor->id] = true;

            if ($moving && (int) $cursor->id === (int) $moving->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Kategori kendi alt dalının altına taşınamaz.',
                ]);
            }

            if (! $cursor->parent_id) {
                break;
            }

            $depth++;
            $cursor = ProductCategory::query()->findOrFail($cursor->parent_id);
        }

        return $depth;
    }

    private function subtreeHeight(ProductCategory $category, array $visited = []): int
    {
        if (isset($visited[$category->id])) {
            throw ValidationException::withMessages([
                'parent_id' => 'Kategori ağacında döngü tespit edildi.',
            ]);
        }

        $visited[$category->id] = true;
        $maxChildHeight = 0;

        foreach ($category->children()->get() as $child) {
            $maxChildHeight = max(
                $maxChildHeight,
                $this->subtreeHeight($child, $visited),
            );
        }

        return 1 + $maxChildHeight;
    }
}
