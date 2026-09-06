<?php

namespace App\Modules\Core\Extraction;

use App\Modules\Core\Models\Attachment;

interface DocumentExtractionProvider
{
    public function provider(): string;

    public function model(): string;

    public function version(): string;

    /**
     * @return array{document_type:string,fields:list<array{key:string,value:mixed,confidence:float}>}
     */
    public function extract(Attachment $attachment): array;
}
