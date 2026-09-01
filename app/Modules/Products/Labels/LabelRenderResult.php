<?php

namespace App\Modules\Products\Labels;

final readonly class LabelRenderResult
{
    public function __construct(
        public int $renderRequestId,
        public string $format,
        public string $mimeType,
        public string $content,
        public string $sha256,
    ) {}
}
