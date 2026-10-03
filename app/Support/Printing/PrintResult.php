<?php

namespace App\Support\Printing;

final readonly class PrintResult
{
    public function __construct(
        public string $content,
        public string $mimeType,
        public string $filename,
    ) {
    }
}
