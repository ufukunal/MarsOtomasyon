<?php

namespace App\Actions\Finance;

use App\Actions\Periods\EnsurePeriodOpen;
use App\Models\Period\BankAccount;
use App\Models\Period\Contact;
use App\Models\Period\ContactTransaction;
use App\Models\Period\Security;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class CreateSecurity
{
    public function __construct(private readonly EnsurePeriodOpen $ensurePeriodOpen) {}

    public function handle(
        string $direction,
        string $kind,
        string $instrumentNo,
        int $contactId,
        string $amount,
        string $transactionDate,
        string $dueDate,
        string $idempotencyKey,
        ?string $bankName = null,
        ?int $bankAccountId = null,
        ?string $note = null,
    ): Security {
        MutationAuthorizer::authorize('securities.create');

        return IdempotencyKey::run(
            $idempotencyKey,
            'security.create',
            function () use (
                $direction,
                $kind,
                $instrumentNo,
                $contactId,
                $amount,
                $transactionDate,
                $dueDate,
                $bankName,
                $bankAccountId,
                $note,
            ): Security {
                if (! in_array($direction, ['incoming', 'outgoing'], true)
                    || ! in_array($kind, ['check', 'note'], true)) {
                    throw new DomainException('Çek/senet yönü veya türü geçersiz.');
                }

                $instrumentNo = trim($instrumentNo);
                $normalized = bcadd($amount, '0', 4);

                if ($instrumentNo === '' || bccomp($normalized, '0', 4) <= 0) {
                    throw new DomainException('Çek/senet numarası ve pozitif tutar zorunludur.');
                }

                $date = CarbonImmutable::parse($transactionDate)->startOfDay();
                $due = CarbonImmutable::parse($dueDate)->startOfDay();

                if ($due->lessThan($date)) {
                    throw new DomainException('Çek/senet vadesi işlem tarihinden önce olamaz.');
                }

                $this->ensurePeriodOpen->handle($date);

                return DB::connection('period')->transaction(function () use (
                    $direction,
                    $kind,
                    $instrumentNo,
                    $contactId,
                    $normalized,
                    $date,
                    $due,
                    $bankName,
                    $bankAccountId,
                    $note,
                ): Security {
                    $contact = Contact::query()->lockForUpdate()->findOrFail($contactId);

                    if ($direction === 'outgoing' && $kind === 'check' && $bankAccountId === null) {
                        throw new DomainException('Verilen çek için banka hesabı zorunludur.');
                    }

                    if ($bankAccountId !== null) {
                        BankAccount::query()->where('is_active', true)->findOrFail($bankAccountId);
                    }

                    $fingerprint = hash('sha256', implode('|', [
                        $direction,
                        $kind,
                        mb_strtoupper($instrumentNo, 'UTF-8'),
                        (string) $contactId,
                        $due->toDateString(),
                        $normalized,
                        trim((string) $bankName),
                        (string) ($bankAccountId ?? 0),
                    ]));

                    if (Security::query()->where('fingerprint', $fingerprint)->exists()) {
                        throw new DomainException('Aynı çek/senet daha önce kaydedilmiş.');
                    }

                    $actor = auth()->user();
                    $security = Security::query()->create([
                        'direction' => $direction,
                        'kind' => $kind,
                        'instrument_no' => $instrumentNo,
                        'fingerprint' => $fingerprint,
                        'contact_id' => $contact->id,
                        'bank_account_id' => $bankAccountId,
                        'bank_name' => trim((string) $bankName) ?: null,
                        'issue_date' => $date->toDateString(),
                        'due_date' => $due->toDateString(),
                        'currency' => 'TRY',
                        'amount' => $normalized,
                        'status' => $direction === 'incoming' ? 'portfolio' : 'issued',
                        'notes' => $note,
                        'created_by' => $actor?->id,
                        'created_by_name' => $actor?->name,
                    ]);

                    ContactTransaction::query()->create([
                        'contact_id' => $contact->id,
                        'transaction_type' => $direction === 'incoming'
                            ? 'security_received'
                            : 'security_issued',
                        'direction' => $direction === 'incoming' ? 'credit' : 'debit',
                        'transaction_date' => $date->toDateString(),
                        'due_date' => $due->toDateString(),
                        'amount' => $normalized,
                        'currency' => 'TRY',
                        'description' => $note ?: $instrumentNo,
                        'created_by' => $actor?->id,
                        'created_by_name' => $actor?->name,
                    ]);

                    AuditContext::period(
                        'Çek/senet kaydı oluşturuldu.',
                        [
                            'security_id' => $security->id,
                            'direction' => $direction,
                            'kind' => $kind,
                            'instrument_no' => $instrumentNo,
                            'contact_id' => $contact->id,
                            'amount' => $normalized,
                        ],
                        $security,
                        'security_created',
                    );

                    return $security;
                }, attempts: 3);
            },
        );
    }
}
