<?php

namespace App\Contracts;

use Illuminate\Database\Eloquent\Builder;

interface SearchIndexed
{
    /** @return array<int, string> */
    public function searchableFields(): array;

    /**
     * @param Builder<static> $query
     * @return Builder<static>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder;
}
