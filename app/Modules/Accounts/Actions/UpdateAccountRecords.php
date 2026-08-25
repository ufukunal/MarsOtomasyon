<?php

namespace App\Modules\Accounts\Actions;

use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountBankAccount;
use App\Modules\Accounts\Models\AccountNote;
use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class UpdateAccountRecords
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private AuditRecorder $audit,
        private AccountBankDataNormalizer $bankNormalizer,
    ) {}

    public function handle(int $accountId, UpdateAccountRecordsData $data): Account
    {
        $companyId = $this->companyId();
        $actorId = $this->actorId();
        $this->assertSubmissionRules($data);

        try {
            return DB::transaction(function () use ($companyId, $actorId, $accountId, $data): Account {
                $account = Account::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($accountId);
                $account->load(['bankAccounts', 'notes']);
                $before = $this->snapshot($account);

                AccountBankAccount::query()
                    ->where('company_id', $companyId)
                    ->where('account_id', $accountId)
                    ->where('is_default', true)
                    ->update(['is_default' => false, 'updated_at' => now()]);

                $this->syncBanks($companyId, $accountId, $data->bankAccounts);
                $this->syncNotes($companyId, $accountId, $actorId, $data->notes);
                $account->load(['bankAccounts', 'notes']);

                $this->audit->record(
                    AuditAction::AccountRecordsUpdated,
                    AuditTargetType::Account,
                    $account->getKey(),
                    before: $before,
                    after: $this->snapshot($account),
                );

                return $account;
            });
        } catch (QueryException $exception) {
            $this->throwConflict($exception);
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function syncBanks(int $companyId, int $accountId, array $rows): void
    {
        $existing = [];
        foreach (AccountBankAccount::query()->where('company_id', $companyId)->where('account_id', $accountId)->get() as $bank) {
            $existing[(int) $bank->getKey()] = $bank;
        }

        $kept = [];
        foreach ($rows as $index => $row) {
            $id = $this->optionalId($row['id'] ?? null, "bank_accounts.$index.id");
            $bank = $id === null ? new AccountBankAccount : ($existing[$id] ?? null);
            if (! $bank instanceof AccountBankAccount) {
                throw ValidationException::withMessages(["bank_accounts.$index.id" => 'Banka hesabı bu cariye ait değil.']);
            }

            $bank->fill([
                'company_id' => $companyId,
                'account_id' => $accountId,
                ...$this->bankNormalizer->normalize($row, $index),
            ]);
            $bank->save();
            $kept[(int) $bank->getKey()] = true;
        }

        foreach ($existing as $id => $bank) {
            if (! isset($kept[$id])) {
                $bank->delete();
            }
        }
    }

    /** @param list<array<string, mixed>> $rows */
    private function syncNotes(int $companyId, int $accountId, int $actorId, array $rows): void
    {
        if ($actorId < 1) {
            throw new LogicException('Account note actor must be a persisted user.');
        }

        $existing = [];
        foreach (AccountNote::query()->where('company_id', $companyId)->where('account_id', $accountId)->get() as $note) {
            $existing[(int) $note->getKey()] = $note;
        }

        $kept = [];
        foreach ($rows as $index => $row) {
            $id = $this->optionalId($row['id'] ?? null, "notes.$index.id");
            $note = $id === null ? new AccountNote : ($existing[$id] ?? null);
            if (! $note instanceof AccountNote) {
                throw ValidationException::withMessages(["notes.$index.id" => 'Not kaydı bu cariye ait değil.']);
            }

            $body = trim(is_string($row['body'] ?? null) ? $row['body'] : '');
            if ($body === '' || mb_strlen($body) > 10_000) {
                throw ValidationException::withMessages(["notes.$index.body" => 'Not 1 ile 10000 karakter arasında olmalıdır.']);
            }

            $note->fill([
                'company_id' => $companyId,
                'account_id' => $accountId,
                'body' => $body,
                'is_pinned' => (bool) ($row['is_pinned'] ?? false),
                'updated_by_user_id' => $actorId,
            ]);
            if (! $note->exists) {
                $note->created_by_user_id = $actorId;
            }
            $note->save();
            $kept[(int) $note->getKey()] = true;
        }

        foreach ($existing as $id => $note) {
            if (! isset($kept[$id])) {
                $note->delete();
            }
        }
    }

    private function assertSubmissionRules(UpdateAccountRecordsData $data): void
    {
        $defaults = 0;
        $ibans = [];
        foreach ($data->bankAccounts as $index => $row) {
            $defaults += (bool) ($row['is_default'] ?? false) ? 1 : 0;
            $iban = $this->bankNormalizer->normalizeIban($row['iban'] ?? null, "bank_accounts.$index.iban");
            if ($iban !== null && isset($ibans[$iban])) {
                throw ValidationException::withMessages(['bank_accounts' => 'Aynı IBAN bir caride birden fazla kez kullanılamaz.']);
            }
            if ($iban !== null) {
                $ibans[$iban] = true;
            }
        }

        if ($defaults > 1) {
            throw ValidationException::withMessages(['bank_accounts' => 'En fazla bir varsayılan banka hesabı seçilebilir.']);
        }
    }

    private function optionalId(mixed $value, string $field): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            throw ValidationException::withMessages([$field => 'Kayıt kimliği geçersiz.']);
        }
        $id = (int) $value;

        return $id > 0 ? $id : throw ValidationException::withMessages([$field => 'Kayıt kimliği geçersiz.']);
    }

    private function throwConflict(QueryException $exception): never
    {
        if ((string) $exception->getCode() !== '23505') {
            throw $exception;
        }

        $message = (string) ($exception->errorInfo[2] ?? $exception->getMessage());
        if (str_contains($message, 'account_bank_accounts_one_default')) {
            throw ValidationException::withMessages(['bank_accounts' => 'En fazla bir varsayılan banka hesabı seçilebilir.']);
        }

        throw ValidationException::withMessages(['bank_accounts' => 'Aynı IBAN bir caride birden fazla kez kullanılamaz.']);
    }

    /** @return array<string, mixed> */
    private function snapshot(Account $account): array
    {
        $banks = [];
        foreach ($account->bankAccounts->sortBy('id') as $bank) {
            $banks[] = [
                'id' => $bank->getKey(),
                'bank_name' => (string) $bank->bank_name,
                'iban' => $bank->iban === null ? null : (string) $bank->iban,
                'account_number' => $bank->account_number === null ? null : (string) $bank->account_number,
                'currency_code' => (string) $bank->currency_code,
                'is_default' => (bool) $bank->is_default,
            ];
        }

        $notes = [];
        foreach ($account->notes->sortBy('id') as $note) {
            $body = (string) $note->body;
            $notes[] = [
                'id' => $note->getKey(),
                'is_pinned' => (bool) $note->is_pinned,
                'body_sha256' => hash('sha256', $body),
                'body_length' => mb_strlen($body),
            ];
        }

        return ['bank_accounts' => $banks, 'notes' => $notes];
    }

    private function companyId(): int
    {
        $id = $this->companyContext->requireCompany()->getKey();

        return is_int($id) ? $id : throw new LogicException('Account records update requires a persisted active company.');
    }

    /** @return int<1, max> */
    private function actorId(): int
    {
        $id = Auth::id();
        if (! is_int($id) || $id < 1) {
            throw new LogicException('Account records update requires an authenticated actor.');
        }

        return $id;
    }
}
