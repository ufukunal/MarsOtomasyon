<?php

namespace App\Modules\Accounts;

use App\Modules\Accounts\Actions\UpdateAccountB2BPolicy;
use App\Modules\Accounts\Actions\UpdateAccountB2BPolicyData;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Company\ActiveCompanyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use LogicException;

final readonly class AccountB2BPolicyController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private UpdateAccountB2BPolicy $updatePolicy,
    ) {}

    public function edit(int $account): View
    {
        $accountModel = $this->account($account);
        $accountModel->load('b2bPolicy');

        return view('accounts.b2b-form', ['account' => $accountModel]);
    }

    public function update(Request $request, int $account): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $updated = $this->updatePolicy->handle($account, new UpdateAccountB2BPolicyData(
            isEnabled: (bool) $validated['is_enabled'],
            allowOrders: (bool) $validated['allow_orders'],
            showStock: (bool) $validated['show_stock'],
            showInvoices: (bool) $validated['show_invoices'],
            showStatement: (bool) $validated['show_statement'],
            allowAddressManagement: (bool) $validated['allow_address_management'],
        ));

        return redirect()->route('customers.show', $updated->getKey())
            ->with('status', 'Cari B2B / bayi erişim politikası güncellendi.');
    }

    /** @return array<string, list<string>> */
    private function rules(): array
    {
        return [
            'is_enabled' => ['required', 'boolean'],
            'allow_orders' => ['required', 'boolean'],
            'show_stock' => ['required', 'boolean'],
            'show_invoices' => ['required', 'boolean'],
            'show_statement' => ['required', 'boolean'],
            'allow_address_management' => ['required', 'boolean'],
        ];
    }

    private function account(int $id): Account
    {
        return Account::query()->where('company_id', $this->companyId())->findOrFail($id);
    }

    private function companyId(): int
    {
        $companyId = $this->companyContext->requireCompany()->getKey();
        if (! is_int($companyId)) {
            throw new LogicException('B2B policy requires a persisted active company.');
        }

        return $companyId;
    }
}
