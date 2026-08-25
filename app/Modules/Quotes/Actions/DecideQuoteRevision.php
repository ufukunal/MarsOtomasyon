<?php

namespace App\Modules\Quotes\Actions;

use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Core\Models\CompanyMembership;
use App\Modules\Quotes\Enums\QuoteStatus;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Quotes\Models\QuoteRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class DecideQuoteRevision
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private AuditRecorder $audit,
    ) {}

    public function approve(int $quoteId, int $revisionId, int $actorUserId, ?string $note = null): Quote
    {
        return $this->decide($quoteId, $revisionId, $actorUserId, QuoteStatus::Approved, $note);
    }

    public function reject(int $quoteId, int $revisionId, int $actorUserId, ?string $note = null): Quote
    {
        return $this->decide($quoteId, $revisionId, $actorUserId, QuoteStatus::Rejected, $note);
    }

    private function decide(
        int $quoteId,
        int $revisionId,
        int $actorUserId,
        QuoteStatus $status,
        ?string $note,
    ): Quote {
        if (! in_array($status, [QuoteStatus::Approved, QuoteStatus::Rejected], true)) {
            throw new InvalidArgumentException('Quote decision status must be approved or rejected.');
        }

        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        $normalizedNote = $note === null ? null : trim($note);
        if ($normalizedNote === '') {
            $normalizedNote = null;
        }

        return DB::transaction(function () use (
            $companyId,
            $quoteId,
            $revisionId,
            $actorUserId,
            $status,
            $normalizedNote,
        ): Quote {
            $quote = Quote::query()
                ->where('company_id', $companyId)
                ->whereKey($quoteId)
                ->lockForUpdate()
                ->first();

            if ($quote === null) {
                throw ValidationException::withMessages(['quote' => 'Teklif aktif şirkette bulunamadı.']);
            }

            if (
                $quote->statusEnum() === $status
                && (int) $quote->selected_revision_id === $revisionId
            ) {
                return $quote->load(['selectedRevision', 'decisionBy']);
            }

            if (! $quote->isDraft()) {
                throw ValidationException::withMessages(['quote' => 'Yalnız taslak teklif için ticari karar verilebilir.']);
            }

            $revision = QuoteRevision::query()
                ->where('company_id', $companyId)
                ->where('quote_id', $quoteId)
                ->whereKey($revisionId)
                ->first();
            if ($revision === null) {
                throw ValidationException::withMessages(['revision' => 'Seçilen teklif revizyonu bu teklife ait değil.']);
            }

            $actorIsActiveMember = CompanyMembership::query()
                ->where('company_id', $companyId)
                ->where('user_id', $actorUserId)
                ->where('is_active', true)
                ->exists();
            if (! $actorIsActiveMember) {
                throw ValidationException::withMessages(['actor' => 'Karar kullanıcısı aktif şirket üyesi değil.']);
            }

            $quote->fill([
                'status' => $status->value,
                'selected_revision_id' => $revisionId,
                'decision_by_user_id' => $actorUserId,
                'decision_at' => now(),
                'decision_note' => $normalizedNote,
                'converted_at' => null,
            ])->save();

            $this->audit->record(
                $status === QuoteStatus::Approved ? AuditAction::QuoteApproved : AuditAction::QuoteRejected,
                AuditTargetType::Quote,
                $quote->getKey(),
                before: ['status' => QuoteStatus::Draft->value],
                after: [
                    'status' => $status->value,
                    'selected_revision_id' => $revisionId,
                    'revision_number' => (int) $revision->revision_number,
                    'decision_by_user_id' => $actorUserId,
                ],
            );

            return $quote->load(['selectedRevision', 'decisionBy']);
        });
    }
}
