<?php

namespace App\Modules\Quotes\Actions;

use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Quotes\Enums\QuoteStatus;
use App\Modules\Quotes\Models\Quote;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CancelQuote
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private AuditRecorder $audit,
    ) {}

    public function handle(int $quoteId): Quote
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();

        return DB::transaction(function () use ($companyId, $quoteId): Quote {
            $quote = Quote::query()
                ->where('company_id', $companyId)
                ->whereKey($quoteId)
                ->lockForUpdate()
                ->first();

            if ($quote === null) {
                throw ValidationException::withMessages(['quote' => 'Teklif aktif şirkette bulunamadı.']);
            }
            if (! $quote->isDraft()) {
                throw ValidationException::withMessages(['quote' => 'Yalnız taslak teklif iptal edilebilir.']);
            }

            $before = ['status' => $quote->statusEnum()->value];
            $quote->status = QuoteStatus::Cancelled->value;
            $quote->save();

            $this->audit->record(
                AuditAction::QuoteCancelled,
                AuditTargetType::Quote,
                $quote->getKey(),
                before: $before,
                after: ['status' => QuoteStatus::Cancelled->value],
            );

            return $quote;
        });
    }
}
