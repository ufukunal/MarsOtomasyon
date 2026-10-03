<?php

namespace App\Support\Import;

final readonly class RowValidationResult
{
    public function __construct(
        public bool $valid,
        public array $errors = [],
    ) {}
}
