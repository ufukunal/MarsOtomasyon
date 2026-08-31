<?php

namespace App\Modules\Accounts;

use App\Modules\Accounts\Actions\UpdateAccountB2BPolicy;
use App\Modules\Accounts\Actions\UpdateAccountB2BPolicyData;
use App\Modules\Accounts\Models\Account;
use App\Modules\B2B\Enums\B2BPermission;
use App\Modules\B2B\Enums\B2BRiskBehavior;
use App\Modules\B2B\Enums\B2BRole;
use App\Modules\B2B\Enums\B2BUserStatus;
use App\Modules\B2B\Models\B2BUser;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use LogicException;

final readonly class AccountB2BPolicyController
{
    public function __construct(private ActiveCompanyContext $companyContext, private UpdateAccountB2BPolicy $updatePolicy) {}

    public function edit(int $account): View
    {
        $accountModel = $this->account($account);
        $accountModel->load('b2bPolicy');

        return view('accounts.b2b-form', [
            'account' => $accountModel,
            'b2bUsers' => B2BUser::query()->where('company_id', $this->companyId())->where('account_id', $account)->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->where('company_id', $this->companyId())->where('is_active', true)->orderBy('name')->get(),
            'roles' => B2BRole::cases(),
            'permissions' => B2BPermission::cases(),
            'statuses' => B2BUserStatus::cases(),
            'riskBehaviors' => B2BRiskBehavior::cases(),
        ]);
    }

    public function update(Request $request, int $account): RedirectResponse
    {
        $this->account($account);

        $validated = $request->validate([
            'is_enabled' => ['required', 'boolean'],
            'allow_orders' => ['required', 'boolean'],
            'show_price' => ['sometimes', 'boolean'],
            'show_stock' => ['required', 'boolean'],
            'show_balance' => ['sometimes', 'boolean'],
            'show_invoices' => ['required', 'boolean'],
            'show_statement' => ['required', 'boolean'],
            'allow_address_management' => ['required', 'boolean'],
            'default_warehouse_id' => ['nullable', 'integer'],
            'risk_behavior' => ['sometimes', Rule::enum(B2BRiskBehavior::class)],
        ]);

        $updated = $this->updatePolicy->handle($account, new UpdateAccountB2BPolicyData(
            isEnabled: (bool) $validated['is_enabled'],
            allowOrders: (bool) $validated['allow_orders'],
            showPrice: (bool) ($validated['show_price'] ?? false),
            showStock: (bool) $validated['show_stock'],
            showBalance: (bool) ($validated['show_balance'] ?? false),
            showInvoices: (bool) $validated['show_invoices'],
            showStatement: (bool) $validated['show_statement'],
            allowAddressManagement: (bool) $validated['allow_address_management'],
            defaultWarehouseId: isset($validated['default_warehouse_id']) ? (int) $validated['default_warehouse_id'] : null,
            riskBehavior: B2BRiskBehavior::from((string) ($validated['risk_behavior'] ?? B2BRiskBehavior::Block->value)),
        ));

        return redirect()->route('customers.show', $updated->getKey())->with('status', 'Cari B2B / bayi erişim politikası güncellendi.');
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
