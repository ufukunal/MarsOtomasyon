<?php

namespace App\Actions\Finance;

use App\Actions\Periods\EnsurePeriodOpen;
use App\Models\Period\ContactTransaction;
use App\Models\Period\Security;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * A financial field must never be edited in place. Reverse its first
 * contact-ledger effect and create a new instrument in a single transaction.
 * Securities with any payroll involvement are intentionally excluded.
 */
final class ReplaceSecurity
{
    public function __construct(
        private readonly EnsurePeriodOpen $ensurePeriodOpen,
        private readonly CreateSecurity $creator,
    ) {}

    /** @param array{instrument_no:string,contact_id:int,amount:string,due_date:string,bank_name?:string|null,bank_account_id?:int|null,notes?:string|null} $changes */
    public function handle(
        Security $security,
        int $expectedVersion,
        array $changes,
        string $reason,
        string $idempotencyKey,
    ): Security {
        MutationAuthorizer::authorize('securities.update');
        MutationAuthorizer::authorize('securities.cancel');
        MutationAuthorizer::authorize('securities.create');
        $reason = trim($reason);
        if ($reason === '') {
            throw new DomainException('Mali düzeltme için gerekçe zorunludur.');
        }
        if (! preg_match('/^\\d{1,14}(?:\\.\\d{1,4})?$/D', (string) ($changes['amount'] ?? ''))
            || bccomp((string) $changes['amount'], '0', 4) <= 0) {
            throw new DomainException('Yeni evrak tutarı geçersiz.');
        }
        if (mb_strlen($reason) > 1000) {
            throw new DomainException('Mali düzeltme gerekçesi en fazla 1000 karakter olabilir.');
        }

        $this->ensurePeriodOpen->handle(now());

        return IdempotencyKey::run($idempotencyKey, 'security.replace:'.$security->id,
            function () use ($security, $expectedVersion, $changes, $reason, $idempotencyKey): Security {
                return DB::connection('period')->transaction(function () use ($security, $expectedVersion, $changes, $reason, $idempotencyKey): Security {
                    $old = Security::query()->lockForUpdate()->findOrFail($security->id);
                    if ((int) $old->version !== $expectedVersion) {
                        throw new DomainException('Evrak başka bir kullanıcı tarafından değiştirildi; yeniden açın.');
                    }
                    if (! in_array($old->status, ['portfolio', 'issued'], true) || $old->last_payroll_id !== null) {
                        throw new DomainException('Bordro veya başka işlem görmüş çek/senet mali alanları değiştirilemez.');
                    }
                    if (!$old->contact_transaction_id) {
                        throw new DomainException('Evrakın ilk cari hareketi bulunamadı.');
                    }
                    $original = ContactTransaction::query()->lockForUpdate()->findOrFail($old->contact_transaction_id);
                    if ($original->contact_id !== $old->contact_id
                        || bccomp((string) $original->amount, (string) $old->amount, 4) !== 0
                        || $original->currency !== 'TRY'
                        || $original->transaction_type !== ($old->direction === 'incoming' ? 'security_received' : 'security_issued')
                        || ($old->direction === 'incoming' && $original->direction !== 'credit')
                        || ($old->direction === 'outgoing' && $original->direction !== 'debit')) {
                        throw new DomainException('İlk cari hareketinin bütünlüğü doğrulanamadı.');
                    }
                    if (ContactTransaction::query()->where('reversal_of_id', $original->id)->exists()) {
                        throw new DomainException('Evrakın ilk cari hareketi zaten terslenmiş.');
                    }
                    $actor = auth()->user();
                    ContactTransaction::query()->create([
                        'contact_id' => $old->contact_id,
                        'transaction_type' => 'security_correction',
                        'direction' => $original->direction === 'debit' ? 'credit' : 'debit',
                        'transaction_date' => now()->toDateString(),
                        'due_date' => $old->due_date->toDateString(),
                        'amount' => $old->amount,
                        'currency' => 'TRY',
                        'reversal_of_id' => $original->id,
                        'description' => $old->instrument_no.' · Mali düzeltme: '.$reason,
                        'created_by' => $actor?->id,
                        'created_by_name' => $actor?->name,
                    ]);

                    // Archive uniqueness safely: cancelled securities keep their
                    // original number, amounts and ledger, but cannot block reissue.
                    $previousFingerprint = (string) $old->fingerprint;
                    $old->updateWithVersion([
                        'status' => 'cancelled',
                        'fingerprint' => hash('sha256', 'cancelled|'.$old->id.'|'.$previousFingerprint),
                        'notes' => trim((string) ($old->notes ?? ''))."\nMali düzeltme: ".$reason,
                    ], $expectedVersion);

                    $replacement = $this->creator->handle(
                        $old->direction,
                        $old->kind,
                        (string) $changes['instrument_no'],
                        (int) $changes['contact_id'],
                        (string) $changes['amount'],
                        now()->toDateString(),
                        (string) $changes['due_date'],
                        hash('sha256', $idempotencyKey.':reissue'),
                        $changes['bank_name'] ?? null,
                        $changes['bank_account_id'] ?? null,
                        $changes['notes'] ?? null,
                    );
                    AuditContext::period('Çek/senet mali alanları ters kayıt ve yeni evrakla düzeltildi.', [
                        'old_security_id' => $old->id,
                        'new_security_id' => $replacement->id,
                        'old_fingerprint' => $previousFingerprint,
                        'reason' => $reason,
                    ], $replacement, 'security_replaced');

                    return $replacement;
                }, attempts: 3);
            });
    }
}
