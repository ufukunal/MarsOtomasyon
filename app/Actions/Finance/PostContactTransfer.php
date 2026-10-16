<?php

namespace App\Actions\Finance;

use App\Actions\Numbering\GenerateDocumentNumber;
use App\Actions\Periods\EnsurePeriodOpen;
use App\Models\Period\Contact;
use App\Models\Period\ContactTransaction;
use App\Models\Period\ContactTransfer;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PostContactTransfer
{
    public function __construct(
        private readonly EnsurePeriodOpen $ensurePeriodOpen,
        private readonly GenerateDocumentNumber $numbers,
    ) {}

    public function handle(
        int $sourceId,
        int $targetId,
        string $balanceType,
        string $amount,
        string $date,
        string $reason,
        string $idempotencyKey,
        bool $overdraftApproved = false,
    ): ContactTransfer {
        MutationAuthorizer::authorize('contact_transfers.create');

        return IdempotencyKey::run(
            $idempotencyKey,
            'contact-transfer.create',
            function () use ($sourceId, $targetId, $balanceType, $amount, $date, $reason, $overdraftApproved): ContactTransfer {
                if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId
                    || ! in_array($balanceType, ['debit', 'credit'], true)) {
                    throw new DomainException('Virman için farklı kaynak/hedef cari ve geçerli yön zorunludur.');
                }
                if (! preg_match('/^\d{1,14}(?:\.\d{1,4})?$/D', $amount)) {
                    throw new DomainException('Virman tutarı geçersiz.');
                }
                $amount = bcadd($amount, '0', 4);
                if (bccomp($amount, '0', 4) <= 0) {
                    throw new DomainException('Virman tutarı pozitif olmalıdır.');
                }
                $date = CarbonImmutable::parse($date)->startOfDay();
                $this->ensurePeriodOpen->handle($date);

                return DB::connection('period')->transaction(function () use (
                    $sourceId, $targetId, $balanceType, $amount, $date, $reason, $overdraftApproved
                ): ContactTransfer {
                    $contacts = Contact::query()->whereIn('id', [$sourceId, $targetId])
                        ->where('is_active', true)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                    $source = $contacts->get($sourceId);
                    $target = $contacts->get($targetId);
                    if (! $source || ! $target) {
                        throw new DomainException('Virman carilerinden biri bulunamadı veya pasif.');
                    }

                    $balance = $source->balance();
                    $available = $balanceType === 'debit'
                        ? (bccomp($balance, '0', 4) > 0 ? $balance : '0.0000')
                        : (bccomp($balance, '0', 4) < 0 ? bcmul($balance, '-1', 4) : '0.0000');

                    $exceeded = bccomp($amount, $available, 4) > 0;
                    if ($exceeded) {
                        if (! $overdraftApproved) {
                            throw new DomainException('Aktarılacak tutar kaynak carinin mevcut bakiyesini aşamaz. Yetkili onayı gerekir.');
                        }
                        // The popup checkbox alone is never an authorization.
                        MutationAuthorizer::authorize('contact_transfers.override_balance');
                        if (trim($reason) === '') {
                            throw new DomainException('Bakiye aşımı için gerekçe zorunludur.');
                        }
                    }

                    $number = $this->numbers->handle('contact_transfer', $date->year);
                    $actor = auth()->user();
                    $sourceDirection = $balanceType === 'debit' ? 'credit' : 'debit';
                    $targetDirection = $balanceType === 'debit' ? 'debit' : 'credit';
                    $text = $number.' · '.trim($reason);

                    $sourceMovement = ContactTransaction::query()->create([
                        'contact_id' => $sourceId,
                        'transaction_type' => 'contact_transfer',
                        'direction' => $sourceDirection,
                        'transaction_date' => $date->toDateString(),
                        'amount' => $amount,
                        'currency' => 'TRY',
                        'description' => $text,
                        'created_by' => $actor?->id,
                        'created_by_name' => $actor?->name,
                    ]);
                    $targetMovement = ContactTransaction::query()->create([
                        'contact_id' => $targetId,
                        'transaction_type' => 'contact_transfer',
                        'direction' => $targetDirection,
                        'transaction_date' => $date->toDateString(),
                        'amount' => $amount,
                        'currency' => 'TRY',
                        'description' => $text,
                        'created_by' => $actor?->id,
                        'created_by_name' => $actor?->name,
                    ]);

                    $transfer = ContactTransfer::query()->create([
                        'number' => $number,
                        'source_contact_id' => $sourceId,
                        'target_contact_id' => $targetId,
                        'source_transaction_id' => $sourceMovement->id,
                        'target_transaction_id' => $targetMovement->id,
                        'balance_type' => $balanceType,
                        'transfer_date' => $date->toDateString(),
                        'amount' => $amount,
                        'currency' => 'TRY',
                        'reason' => trim($reason),
                        'overdraft_approved' => $exceeded && $overdraftApproved,
                        'status' => 'posted',
                        'created_by' => $actor?->id,
                        'created_by_name' => $actor?->name,
                    ]);
                    AuditContext::period('Cari virman kaydı oluşturuldu.', [
                        'transfer_id' => $transfer->id,
                        'source_contact_id' => $sourceId,
                        'target_contact_id' => $targetId,
                        'amount' => $amount,
                        'balance_type' => $balanceType,
                        'overdraft_approved' => $exceeded && $overdraftApproved,
                    ], $sourceMovement, 'contact_transfer_posted');

                    return $transfer;
                }, attempts: 3);
            },
        );
    }
}
