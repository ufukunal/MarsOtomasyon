<?php

namespace App\Support\Import;

final readonly class RowValidationResult
{
    /** @param array<string, string> $errors */
    public function __construct(
        public bool $valid,
        public array $errors = [],
    ) {}
}
