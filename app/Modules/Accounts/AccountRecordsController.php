<?php

namespace App\Modules\Accounts;

use App\Modules\Accounts\Actions\UpdateAccountRecords;
use App\Modules\Accounts\Actions\UpdateAccountRecordsData;
use App\Modules\Accounts\Files\AccountFileManager;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Models\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class AccountRecordsController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private UpdateAccountRecords $updateRecords,
        private AccountFileManager $files,
    ) {}

    public function edit(int $account): View
    {
        $accountModel = $this->account($account);
        $accountModel->load(['bankAccounts', 'notes.createdBy', 'notes.updatedBy']);

        return view('accounts.records-form', [
            'account' => $accountModel,
            'currencies' => $this->currencies($accountModel),
            'attachments' => $this->files->all($account),
        ]);
    }

    public function update(Request $request, int $account): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $updated = $this->updateRecords->handle($account, new UpdateAccountRecordsData($validated));

        return redirect()->route('customers.show', $updated->getKey())
            ->with('status', 'Cari banka ve not bilgileri güncellendi.');
    }

    public function uploadFile(Request $request, int $account): RedirectResponse
    {
        $this->account($account);
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:51200'],
            'label' => ['nullable', 'string', 'max:160'],
        ]);
        $upload = $request->file('file');
        if (! $upload instanceof UploadedFile) {
            abort(422, 'Dosya yükleme isteği geçersiz.');
        }

        $this->files->upload($account, $upload, isset($validated['label']) ? (string) $validated['label'] : null);

        return redirect()->route('customers.records.edit', $account)
            ->with('status', 'Cari dosyası private storage alanına yüklendi.');
    }

    public function downloadFile(int $account, int $attachment): StreamedResponse
    {
        return $this->files->download($account, $attachment);
    }

    public function detachFile(int $account, int $attachment): RedirectResponse
    {
        $this->files->detach($account, $attachment);

        return redirect()->route('customers.records.edit', $account)
            ->with('status', 'Cari dosya bağlantısı kaldırıldı. Orijinal dosya arşivde korunuyor.');
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'bank_accounts' => ['sometimes', 'array', 'max:20'],
            'bank_accounts.*.id' => ['nullable', 'integer', 'min:1'],
            'bank_accounts.*.bank_name' => ['required', 'string', 'max:160'],
            'bank_accounts.*.branch_name' => ['nullable', 'string', 'max:120'],
            'bank_accounts.*.account_holder' => ['nullable', 'string', 'max:200'],
            'bank_accounts.*.iban' => ['nullable', 'string', 'max:64'],
            'bank_accounts.*.account_number' => ['nullable', 'string', 'max:64'],
            'bank_accounts.*.swift_code' => ['nullable', 'string', 'max:16'],
            'bank_accounts.*.currency_code' => ['required', 'string', 'size:3'],
            'bank_accounts.*.is_default' => ['sometimes', 'boolean'],
            'bank_accounts.*.note' => ['nullable', 'string', 'max:500'],
            'notes' => ['sometimes', 'array', 'max:100'],
            'notes.*.id' => ['nullable', 'integer', 'min:1'],
            'notes.*.body' => ['required', 'string', 'max:10000'],
            'notes.*.is_pinned' => ['sometimes', 'boolean'],
        ];
    }

    private function account(int $id): Account
    {
        return Account::query()->where('company_id', $this->companyId())->findOrFail($id);
    }

    /** @return Collection<int, Currency> */
    private function currencies(Account $account): Collection
    {
        $used = $account->bankAccounts->pluck('currency_code')->filter()->values()->all();

        return Currency::query()
            ->where(function (Builder $query) use ($used): void {
                $query->where('is_active', true);
                if ($used !== []) {
                    $query->orWhereIn('code', $used);
                }
            })
            ->orderBy('code')
            ->get();
    }

    private function companyId(): int
    {
        $id = $this->companyContext->requireCompany()->getKey();

        return is_int($id) ? $id : throw new LogicException('Account records management requires a persisted active company.');
    }
}
