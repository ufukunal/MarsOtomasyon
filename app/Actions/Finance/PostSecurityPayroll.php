<?php

namespace App\Actions\Finance;

use App\Actions\Numbering\GenerateDocumentNumber;
use App\Actions\Periods\EnsurePeriodOpen;
use App\Models\Period\BankAccount;
use App\Models\Period\BankMovement;
use App\Models\Period\Contact;
use App\Models\Period\ContactTransaction;
use App\Models\Period\Security;
use App\Models\Period\SecurityPayroll;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class PostSecurityPayroll
{
    public function __construct(
        private readonly EnsurePeriodOpen $ensurePeriodOpen,
        private readonly GenerateDocumentNumber $numbers,
    ) {}

    /** @param list<int> $securityIds */
    public function handle(
        string $action,
        array $securityIds,
        string $payrollDate,
        string $idempotencyKey,
        ?int $contactId = null,
        ?int $bankAccountId = null,
        ?string $note = null,
    ): SecurityPayroll {
        MutationAuthorizer::authorize('security_payrolls.create');

        return IdempotencyKey::run(
            $idempotencyKey,
            'security-payroll.post',
            function () use (
                $action,
                $securityIds,
                $payrollDate,
                $contactId,
                $bankAccountId,
                $note,
            ): SecurityPayroll {
                if (! in_array($action, [
                    'endorsement',
                    'bank_deposit',
                    'collection',
                    'payment',
                    'return',
                    'protest',
                ], true)) {
                    throw new DomainException('Çek/senet bordro işlem türü geçersiz.');
                }

                $ids = array_values(array_unique(array_map('intval', $securityIds)));

                if ($ids === []) {
                    throw new DomainException('Bordroya en az bir çek/senet eklenmelidir.');
                }

                $date = CarbonImmutable::parse($payrollDate)->startOfDay();
                $this->ensurePeriodOpen->handle($date);

                return DB::connection('period')->transaction(function () use (
                    $action,
                    $ids,
                    $date,
                    $contactId,
                    $bankAccountId,
                    $note,
                ): SecurityPayroll {
                    $securities = Security::query()
                        ->whereIn('id', $ids)
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    if ($securities->count() !== count($ids)) {
                        throw new DomainException('Bordrodaki çek/senetlerden biri bulunamadı.');
                    }

                    $currency = (string) $securities->first()->currency;

                    foreach ($securities as $security) {
                        if ($security->currency !== $currency) {
                            throw new DomainException('Tek bordroda farklı para birimleri kullanılamaz.');
                        }
                    }

                    $contact = $contactId === null
                        ? null
                        : Contact::query()->lockForUpdate()->findOrFail($contactId);
                    $bank = $bankAccountId === null
                        ? null
                        : BankAccount::query()->where('is_active', true)->lockForUpdate()->findOrFail($bankAccountId);

                    if ($bank !== null && $bank->currency !== $currency) {
                        throw new DomainException('Bordro banka hesabı para birimi çek/senetlerle eşleşmiyor.');
                    }

                    $this->assertActionContext($action, $contact, $bank);

                    $total = '0.0000';

                    foreach ($securities as $security) {
                        $this->assertSecurityState($security, $action);
                        $total = bcadd($total, (string) $security->amount, 4);
                    }

                    $actor = auth()->user();
                    $payroll = SecurityPayroll::query()->create([
                        'number' => $this->numbers->handle('security_payroll', $date->year),
                        'action' => $action,
                        'contact_id' => $contact?->id,
                        'bank_account_id' => $bank?->id,
                        'payroll_date' => $date->toDateString(),
                        'currency' => $currency,
                        'total_amount' => $total,
                        'security_ids' => $ids,
                        'status' => 'posted',
                        'notes' => $note,
                        'created_by' => $actor?->id,
                        'created_by_name' => $actor?->name,
                    ]);

                    foreach ($securities as $security) {
                        $this->applySecurityAction(
                            $security,
                            $payroll,
                            $contact,
                            $bank,
                            $date->toDateString(),
                            $actor?->id,
                            $actor?->name,
                        );
                    }

                    AuditContext::period(
                        'Çek/senet bordrosu kesinleştirildi.',
                        [
                            'payroll_id' => $payroll->id,
                            'number' => $payroll->number,
                            'action' => $action,
                            'security_ids' => $ids,
                            'total_amount' => $total,
                        ],
                        $payroll,
                        'security_payroll_posted',
                    );

                    return $payroll;
                }, attempts: 3);
            },
        );
    }

    private function assertActionContext(
        string $action,
        ?Contact $contact,
        ?BankAccount $bank,
    ): void {
        if ($action === 'endorsement' && $contact === null) {
            throw new DomainException('Ciro bordrosunda hedef cari zorunludur.');
        }

        if (in_array($action, ['bank_deposit', 'collection', 'payment'], true) && $bank === null) {
            throw new DomainException('Bu çek/senet işlemi için banka hesabı zorunludur.');
        }
    }

    private function assertSecurityState(Security $security, string $action): void
    {
        $valid = match ($action) {
            'endorsement' => $security->direction === 'incoming' && $security->status === 'portfolio',
            'bank_deposit' => $security->direction === 'incoming' && $security->status === 'portfolio',
            'collection' => $security->direction === 'incoming'
                && in_array($security->status, ['portfolio', 'banked'], true),
            'payment' => $security->direction === 'outgoing' && $security->status === 'issued',
            'return' => $security->direction === 'incoming'
                && in_array($security->status, ['portfolio', 'banked'], true),
            'protest' => $security->direction === 'incoming'
                && in_array($security->status, ['portfolio', 'banked'], true),
            default => false,
        };

        if (! $valid) {
            throw new DomainException("{$security->instrument_no} mevcut durumda {$action} işlemine uygun değil.");
        }
    }

    private function applySecurityAction(
        Security $security,
        SecurityPayroll $payroll,
        ?Contact $contact,
        ?BankAccount $bank,
        string $date,
        ?int $actorId,
        ?string $actorName,
    ): void {
        match ($payroll->action) {
            'endorsement' => $this->endorse($security, $payroll, $contact, $date, $actorId, $actorName),
            'bank_deposit' => $this->bankDeposit($security, $payroll, $bank),
            'collection' => $this->collect($security, $payroll, $bank, $date, $actorId, $actorName),
            'payment' => $this->pay($security, $payroll, $bank, $date, $actorId, $actorName),
            'return' => $this->returnToContact($security, $payroll, $date, $actorId, $actorName),
            'protest' => $this->changeStatus($security, $payroll, 'protested'),
            default => null,
        };
    }

    private function endorse(
        Security $security,
        SecurityPayroll $payroll,
        ?Contact $contact,
        string $date,
        ?int $actorId,
        ?string $actorName,
    ): void {
        if ($contact === null) {
            throw new DomainException('Ciro hedef carisi bulunamadı.');
        }

        ContactTransaction::query()->create([
            'contact_id' => $contact->id,
            'transaction_type' => 'security_endorsement',
            'direction' => 'debit',
            'transaction_date' => $date,
            'due_date' => $security->due_date,
            'amount' => $security->amount,
            'currency' => 'TRY',
            'description' => $payroll->number.' · '.$security->instrument_no,
            'created_by' => $actorId,
            'created_by_name' => $actorName,
        ]);

        $security->updateWithVersion([
            'endorsed_to_contact_id' => $contact->id,
            'last_payroll_id' => $payroll->id,
            'status' => 'endorsed',
        ], (int) $security->version);
    }

    private function bankDeposit(Security $security, SecurityPayroll $payroll, ?BankAccount $bank): void
    {
        if ($bank === null) {
            throw new DomainException('Bankaya verme hesabı bulunamadı.');
        }

        $security->updateWithVersion([
            'bank_account_id' => $bank->id,
            'last_payroll_id' => $payroll->id,
            'status' => 'banked',
        ], (int) $security->version);
    }

    private function collect(
        Security $security,
        SecurityPayroll $payroll,
        ?BankAccount $bank,
        string $date,
        ?int $actorId,
        ?string $actorName,
    ): void {
        if ($bank === null) {
            throw new DomainException('Tahsil banka hesabı bulunamadı.');
        }

        if ($security->status === 'banked'
            && $security->bank_account_id !== null
            && (int) $security->bank_account_id !== (int) $bank->id) {
            throw new DomainException('Bankaya verilmiş çek farklı bir banka hesabından tahsil edilemez.');
        }

        BankMovement::query()->create([
            'bank_account_id' => $bank->id,
            'movement_date' => $date,
            'direction' => 'in',
            'movement_type' => 'security_collection',
            'amount' => $security->amount,
            'origin' => 'book',
            'reference' => $security->instrument_no,
            'group_key' => $payroll->number,
            'description' => $payroll->notes,
            'metadata' => ['security_id' => $security->id, 'payroll_id' => $payroll->id],
            'created_by' => $actorId,
            'created_by_name' => $actorName,
        ]);

        $security->updateWithVersion([
            'bank_account_id' => $bank->id,
            'last_payroll_id' => $payroll->id,
            'status' => 'collected',
        ], (int) $security->version);
    }

    private function pay(
        Security $security,
        SecurityPayroll $payroll,
        ?BankAccount $bank,
        string $date,
        ?int $actorId,
        ?string $actorName,
    ): void {
        if ($bank === null) {
            throw new DomainException('Ödeme banka hesabı bulunamadı.');
        }

        if ($security->bank_account_id !== null
            && (int) $security->bank_account_id !== (int) $bank->id) {
            throw new DomainException('Verilen çek tanımlı olduğu banka hesabından ödenmelidir.');
        }

        BankMovement::query()->create([
            'bank_account_id' => $bank->id,
            'movement_date' => $date,
            'direction' => 'out',
            'movement_type' => 'security_payment',
            'amount' => $security->amount,
            'origin' => 'book',
            'reference' => $security->instrument_no,
            'group_key' => $payroll->number,
            'description' => $payroll->notes,
            'metadata' => ['security_id' => $security->id, 'payroll_id' => $payroll->id],
            'created_by' => $actorId,
            'created_by_name' => $actorName,
        ]);

        $security->updateWithVersion([
            'bank_account_id' => $bank->id,
            'last_payroll_id' => $payroll->id,
            'status' => 'paid',
        ], (int) $security->version);
    }

    private function returnToContact(
        Security $security,
        SecurityPayroll $payroll,
        string $date,
        ?int $actorId,
        ?string $actorName,
    ): void {
        if ($security->contact_id === null) {
            throw new DomainException('İade edilecek çek/senet kaynak carisi bulunamadı.');
        }

        ContactTransaction::query()->create([
            'contact_id' => $security->contact_id,
            'transaction_type' => 'security_return',
            'direction' => 'debit',
            'transaction_date' => $date,
            'due_date' => $security->due_date,
            'amount' => $security->amount,
            'currency' => 'TRY',
            'description' => $payroll->number.' · '.$security->instrument_no,
            'created_by' => $actorId,
            'created_by_name' => $actorName,
        ]);

        $this->changeStatus($security, $payroll, 'returned');
    }

    private function changeStatus(
        Security $security,
        SecurityPayroll $payroll,
        string $status,
    ): void {
        $security->updateWithVersion([
            'last_payroll_id' => $payroll->id,
            'status' => $status,
        ], (int) $security->version);
    }
}
