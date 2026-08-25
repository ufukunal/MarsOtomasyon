<?php

namespace App\Modules\Accounts;

use App\Modules\Accounts\Actions\CreateAccount;
use App\Modules\Accounts\Actions\CreateAccountData;
use App\Modules\Accounts\Actions\UpdateAccount;
use App\Modules\Accounts\Actions\UpdateAccountData;
use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Enums\TaxIdentityType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Models\Currency;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use LogicException;

final readonly class AccountController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private CreateAccount $createAccount,
        private UpdateAccount $updateAccount,
    ) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', 'all');
        if (! in_array($status, ['all', AccountStatus::Active->value, AccountStatus::Inactive->value], true)) {
            $status = 'all';
        }

        $query = Account::query()
            ->where('company_id', $this->companyId());

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function (Builder $builder) use ($like): void {
                $builder
                    ->whereRaw('code ILIKE ?', [$like])
                    ->orWhereRaw('legal_name ILIKE ?', [$like])
                    ->orWhereRaw('trade_name ILIKE ?', [$like]);
            });
        }

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        return view('accounts.index', [
            'accounts' => $query
                ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
                ->orderBy('legal_name')
                ->orderBy('code')
                ->paginate(50)
                ->withQueryString(),
            'search' => $search,
            'statusFilter' => $status,
        ]);
    }

    public function create(): View
    {
        return view('accounts.form', [
            'account' => null,
            'currencies' => $this->currencies(),
            'accountTypes' => AccountType::cases(),
            'accountStatuses' => AccountStatus::cases(),
            'taxIdentityTypes' => TaxIdentityType::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules(includeStatus: false));

        $account = $this->createAccount->handle(new CreateAccountData(
            code: (string) $validated['code'],
            type: AccountType::from((string) $validated['type']),
            legalName: (string) $validated['legal_name'],
            tradeName: $this->nullableString($validated['trade_name'] ?? null),
            taxIdentityType: TaxIdentityType::from((string) $validated['tax_identity_type']),
            taxNumber: $this->nullableString($validated['tax_number'] ?? null),
            taxOffice: $this->nullableString($validated['tax_office'] ?? null),
            bookCurrencyCode: (string) $validated['book_currency_code'],
            dueDays: (int) $validated['due_days'],
            discountRate: (string) $validated['discount_rate'],
            riskLimit: (string) $validated['risk_limit'],
        ));

        return redirect()->route('customers.show', $account->getKey())
            ->with('status', 'Cari oluşturuldu.');
    }

    public function show(int $account): View
    {
        return view('accounts.show', [
            'account' => $this->account($account),
        ]);
    }

    public function edit(int $account): View
    {
        $account = $this->account($account);

        return view('accounts.form', [
            'account' => $account,
            'currencies' => $this->currencies($account),
            'accountTypes' => AccountType::cases(),
            'accountStatuses' => AccountStatus::cases(),
            'taxIdentityTypes' => TaxIdentityType::cases(),
        ]);
    }

    public function update(Request $request, int $account): RedirectResponse
    {
        $validated = $request->validate($this->rules(includeStatus: true));

        $updated = $this->updateAccount->handle($account, new UpdateAccountData(
            code: (string) $validated['code'],
            type: AccountType::from((string) $validated['type']),
            status: AccountStatus::from((string) $validated['status']),
            legalName: (string) $validated['legal_name'],
            tradeName: $this->nullableString($validated['trade_name'] ?? null),
            taxIdentityType: TaxIdentityType::from((string) $validated['tax_identity_type']),
            taxNumber: $this->nullableString($validated['tax_number'] ?? null),
            taxOffice: $this->nullableString($validated['tax_office'] ?? null),
            bookCurrencyCode: (string) $validated['book_currency_code'],
            dueDays: (int) $validated['due_days'],
            discountRate: (string) $validated['discount_rate'],
            riskLimit: (string) $validated['risk_limit'],
        ));

        return redirect()->route('customers.show', $updated->getKey())
            ->with('status', 'Cari güncellendi.');
    }

    /** @return array<string, mixed> */
    private function rules(bool $includeStatus): array
    {
        $rules = [
            'code' => ['required', 'string', 'max:64'],
            'type' => ['required', Rule::enum(AccountType::class)],
            'legal_name' => ['required', 'string', 'max:200'],
            'trade_name' => ['nullable', 'string', 'max:200'],
            'tax_identity_type' => ['required', Rule::enum(TaxIdentityType::class)],
            'tax_number' => ['nullable', 'string', 'max:32'],
            'tax_office' => ['nullable', 'string', 'max:120'],
            'book_currency_code' => ['required', 'string', 'size:3'],
            'due_days' => ['required', 'integer', 'min:0', 'max:3650'],
            'discount_rate' => ['required', 'decimal:0,6', 'min:0', 'max:100'],
            'risk_limit' => ['required', 'decimal:0,6', 'min:0'],
        ];

        if ($includeStatus) {
            $rules['status'] = ['required', Rule::enum(AccountStatus::class)];
        }

        return $rules;
    }

    private function account(int $id): Account
    {
        return Account::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($id);
    }

    /** @return Collection<int, Currency> */
    private function currencies(?Account $account = null): Collection
    {
        return Currency::query()
            ->where(function (Builder $query) use ($account): void {
                $query->where('is_active', true);
                if ($account !== null) {
                    $query->orWhere('code', (string) $account->book_currency_code);
                }
            })
            ->orderBy('code')
            ->get();
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }

    private function companyId(): int
    {
        $companyId = $this->companyContext->requireCompany()->getKey();
        if (! is_int($companyId)) {
            throw new LogicException('Account management requires a persisted active company.');
        }

        return $companyId;
    }
}
