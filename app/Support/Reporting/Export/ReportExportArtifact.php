<?php

namespace App\Support\Reporting\Export;

final readonly class ReportExportArtifact
{
    public function __construct(
        public string $format,
        public string $filename,
        public string $mimeType,
        public string $contents,
    ) {}
}
