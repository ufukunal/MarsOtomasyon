<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TModel of Model
 */
interface SearchIndexed
{
    /** @return array<int, string> */
    public function searchableFields(): array;

    /**
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder;
}
