<?php

namespace App\Support\Search;

use App\Contracts\SearchIndexed;
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
            if (! $model instanceof SearchIndexed) {
                return;
            }

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

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
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
