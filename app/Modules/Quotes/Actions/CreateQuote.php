<?php

namespace App\Modules\Quotes\Actions;

use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Core\Enums\DocumentType;
use App\Modules\Core\Numbering\DocumentNumberIssuer;
use App\Modules\Quotes\Enums\QuoteStatus;
use App\Modules\Quotes\Models\Quote;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateQuote
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private QuoteDraftResolver $resolver,
        private DocumentNumberIssuer $numbers,
        private AuditRecorder $audit,
    ) {}

    public function handle(QuoteDraftData $data, string $seriesCode = 'default'): Quote
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        $seriesCode = mb_strtolower(trim($seriesCode));
        if (preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/D', $seriesCode) !== 1 || strlen($seriesCode) > 64) {
            throw ValidationException::withMessages(['series_code' => 'Teklif numara serisi canonical ve en fazla 64 karakter olmalıdır.']);
        }

        $draft = $this->resolver->resolve($companyId, $data);

        try {
            return DB::transaction(function () use ($companyId, $seriesCode, $draft): Quote {
                $number = $this->numbers->issue($companyId, DocumentType::Quote, $seriesCode);
                $calculation = $draft->calculation;

                $quote = Quote::query()->create([
                    'company_id' => $companyId,
                    'account_id' => $draft->accountId,
                    'number' => $number->number,
                    'series_code' => $number->seriesCode,
                    'sequence_value' => $number->sequenceValue,
                    'status' => QuoteStatus::Draft,
                    'quote_date' => $draft->quoteDate,
                    'valid_until' => $draft->validUntil,
                    'currency_code' => $draft->currencyCode,
                    'document_discount_rate' => $draft->documentDiscountRate,
                    'base_net_total' => $calculation->baseNet,
                    'line_discount_total' => $calculation->lineDiscountNet,
                    'document_discount_total' => $calculation->documentDiscountNet,
                    'net_total' => $calculation->net,
                    'tax_total' => $calculation->tax,
                    'gross_total' => $calculation->gross,
                    'note' => $draft->note,
                ]);

                $this->persistLines($quote, $draft);
                $this->audit->record(
                    AuditAction::QuoteCreated,
                    AuditTargetType::Quote,
                    $quote->getKey(),
                    after: $this->snapshot($quote),
                );

                return $quote->load('lines');
            });
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['series_code' => $exception->getMessage()]);
        }
    }

    private function persistLines(Quote $quote, ResolvedQuoteDraft $draft): void
    {
        foreach ($draft->lines as $line) {
            $result = $line->calculation;
            $quote->lines()->create([
                'company_id' => $quote->company_id,
                'position' => $line->position,
                'product_id' => $line->productId,
                'product_code' => $line->productCode,
                'description' => $line->description,
                'quantity' => $result->quantity,
                'price_basis' => $result->priceBasis,
                'unit_price' => $result->unitPrice,
                'line_discount_rate' => $result->lineDiscountRate,
                'tax_id' => $line->taxId,
                'tax_rate' => $result->taxRate,
                'tax_zero_reason_id' => $line->taxZeroReasonId,
                'tax_zero_reason_code' => $result->taxZeroReasonCode,
                'base_net' => $result->baseNet,
                'line_discount_net' => $result->lineDiscountNet,
                'document_discount_net' => $result->documentDiscountNet,
                'net_total' => $result->net,
                'tax_total' => $result->tax,
                'gross_total' => $result->gross,
            ]);
        }
    }

    /** @return array<string, int|string|null> */
    private function snapshot(Quote $quote): array
    {
        return [
            'number' => (string) $quote->number,
            'account_id' => (int) $quote->account_id,
            'status' => $quote->statusEnum()->value,
            'currency_code' => (string) $quote->currency_code,
            'net_total' => (string) $quote->net_total,
            'tax_total' => (string) $quote->tax_total,
            'gross_total' => (string) $quote->gross_total,
        ];
    }
}
