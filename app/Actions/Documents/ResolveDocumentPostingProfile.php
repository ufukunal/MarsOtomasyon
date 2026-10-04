<?php

namespace App\Actions\Documents;

use App\DataObjects\Documents\DocumentPostingContext;
use App\DataObjects\Documents\PostingProfile;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use DomainException;

final class ResolveDocumentPostingProfile
{
    public function handle(Document $document, ?DocumentPostingContext $context = null): PostingProfile
    {
        return match ($document->document_type) {
            DocumentType::Dispatch => new PostingProfile('line_calculated', true, true, null, false),
            DocumentType::SalesInvoice => new PostingProfile('line_calculated', true, true, 'debit', false),
            DocumentType::Collection => new PostingProfile('header_amount', false, false, 'credit', true),
            DocumentType::ContactDebitCredit => new PostingProfile(
                'header_amount',
                false,
                false,
                $this->direction($context),
                false,
            ),
            default => throw new DomainException('Bu belge tipi PostDocument ile kesinleştirilemez.'),
        };
    }

    private function direction(?DocumentPostingContext $context): string
    {
        $direction = $context?->contactDirection;

        if (! in_array($direction, ['debit', 'credit'], true)) {
            throw new DomainException('Cari borç/alacak fişinde doğrulanmış debit|credit yönü zorunludur.');
        }

        return $direction;
    }
}
