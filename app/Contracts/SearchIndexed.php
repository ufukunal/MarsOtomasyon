<?php

namespace App\Contracts;

interface SearchIndexed
{
    /** @return array<int, string> */
    public function searchableFields(): array;
}
