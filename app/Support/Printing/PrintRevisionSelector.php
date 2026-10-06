<?php

namespace App\Support\Printing;

use App\Models\DocumentTemplate;
use App\Support\Period\PeriodContext;
use DomainException;

final class PrintRevisionSelector
{
    public const CURRENT = 'current';

    public const ORIGINAL = 'original';

    public function resolve(
        string $templateKey,
        string $selection,
        ?int $originalRevisionNo = null,
    ): DocumentTemplate {
        PeriodContext::ensure();

        $query = DocumentTemplate::query()
            ->where('company_id', PeriodContext::companyId())
            ->where('template_key', $templateKey);

        if ($selection === self::CURRENT) {
            $template = $query
                ->where('is_active', true)
                ->where('is_default', true)
                ->first();
        } elseif ($selection === self::ORIGINAL) {
            if (! $originalRevisionNo || $originalRevisionNo < 1) {
                throw new DomainException('Original revision seçimi revision_no gerektirir.');
            }

            $template = $query
                ->where('revision_no', $originalRevisionNo)
                ->first();
        } else {
            throw new DomainException('Reprint revision seçimi current veya original olmalıdır.');
        }

        if (! $template) {
            throw new DomainException('Seçilen print template revizyonu bulunamadı.');
        }

        return $template;
    }
}
