<?php

namespace App\Support\Reporting;

final readonly class ReportRequest
{
    /**
     * @param  array<string,mixed>  $filters
     * @param  list<string>|null  $columns
     * @param  list<array{key:string,direction?:string}|ReportSort>  $sort
     */
    public function __construct(
        public array $filters = [],
        public ?array $columns = null,
        public array $sort = [],
        public int $limit = 100,
        public int $offset = 0,
    ) {}
}
