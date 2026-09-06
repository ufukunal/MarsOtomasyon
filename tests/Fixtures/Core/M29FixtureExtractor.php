<?php

namespace Tests\Fixtures\Core;

use App\Modules\Core\Extraction\DocumentExtractionProvider;
use App\Modules\Core\Models\Attachment;

final class M29FixtureExtractor implements DocumentExtractionProvider
{
    public int $calls = 0;

    public function provider(): string
    {
        return 'fixture';
    }

    public function model(): string
    {
        return 'fixture-ocr';
    }

    public function version(): string
    {
        return '1';
    }

    public function extract(Attachment $attachment): array
    {
        $this->calls++;

        return [
            'document_type' => 'supplier_invoice',
            'fields' => [
                ['key' => 'invoice_no', 'value' => 'INV-29', 'confidence' => 0.99],
                ['key' => 'total', 'value' => '12O.00', 'confidence' => 0.61],
            ],
        ];
    }
}
