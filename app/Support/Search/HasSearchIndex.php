<?php

namespace App\Support\Search;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 */
trait HasSearchIndex
{
    /**
     * @return array<int, string>
     */
    abstract public function searchableFields(): array;

    protected static function bootHasSearchIndex(): void
    {
        static::saving(function (Model $model): void {
            $parts = array_map(
                fn (string $field): string => (string) ($model->getAttribute($field) ?? ''),
                $model->searchableFields(),
            );

            $model->setAttribute(
                'search_index',
                SearchNormalizer::make(implode(' ', $parts)),
            );
        });
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $normalized = SearchNormalizer::make($term);

        if ($normalized === '') {
            return $query;
        }

        foreach (explode(' ', $normalized) as $token) {
            $query->where('search_index', 'like', '%'.$token.'%');
        }

        return $query;
    }
}
