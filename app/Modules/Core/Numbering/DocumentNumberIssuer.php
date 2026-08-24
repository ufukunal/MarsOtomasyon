<?php

namespace App\Modules\Core\Numbering;

use App\Modules\Core\Enums\DocumentType;
use App\Modules\Core\Models\DocumentSequence;
use DomainException;
use Illuminate\Support\Facades\DB;

final class DocumentNumberIssuer
{
    public function issue(int $companyId, DocumentType $documentType, string $seriesCode = 'default'): IssuedDocumentNumber
    {
        if (DB::transactionLevel() < 1) {
            throw new DomainException('Belge numarası business transaction dışında üretilemez.');
        }

        $seriesCode = mb_strtolower(trim($seriesCode));

        $sequence = DocumentSequence::query()
            ->where('company_id', $companyId)
            ->where('document_type', $documentType->value)
            ->where('series_code', $seriesCode)
            ->where('is_active', true)
            ->lockForUpdate()
            ->first();

        if ($sequence === null) {
            throw new DomainException('Aktif belge numara serisi bulunamadı.');
        }

        $issuedValue = $sequence->next_value;
        $number = $sequence->prefix.str_pad((string) $issuedValue, $sequence->padding, '0', STR_PAD_LEFT);

        $sequence->next_value = $issuedValue + 1;
        $sequence->save();

        return new IssuedDocumentNumber(
            documentType: $documentType,
            seriesCode: $seriesCode,
            sequenceValue: $issuedValue,
            number: $number,
        );
    }
}
