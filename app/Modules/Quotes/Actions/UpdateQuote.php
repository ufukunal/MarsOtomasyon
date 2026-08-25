<?php

namespace App\Modules\Quotes\Actions;

use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Quotes\Models\Quote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateQuote
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private QuoteDraftResolver $resolver,
        private AuditRecorder $audit,
    ) {}

    public function handle(int $quoteId, QuoteDraftData $data): Quote
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        $draft = $this->resolver->resolve($companyId, $data);

        return DB::transaction(function () use ($companyId, $quoteId, $draft): Quote {
            $quote = Quote::query()
                ->where('company_id', $companyId)
                ->whereKey($quoteId)
                ->lockForUpdate()
                ->first();

            if ($quote === null) {
                throw ValidationException::withMessages(['quote' => 'Teklif aktif şirkette bulunamadı.']);
            }
            if (! $quote->isDraft()) {
                throw ValidationException::withMessages(['quote' => 'Yalnız taslak teklifler düzenlenebilir.']);
            }

            $before = $this->snapshot($quote);
            $calculation = $draft->calculation;
            $quote->fill([
                'account_id' => $draft->accountId,
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
            ])->save();

            $quote->lines()->delete();
            foreach ($draft->lines as $line) {
                $result = $line->calculation;
                $quote->lines()->create([
                    'company_id' => $companyId,
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

            $this->audit->record(
                AuditAction::QuoteUpdated,
                AuditTargetType::Quote,
                $quote->getKey(),
                before: $before,
                after: $this->snapshot($quote),
            );

            return $quote->load('lines');
        });
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
