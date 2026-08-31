<?php

namespace App\Modules\Accounts\Actions;

use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountB2BPolicy;
use App\Modules\B2B\Enums\B2BRiskBehavior;
use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Inventory\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class UpdateAccountB2BPolicy
{
    public function __construct(private ActiveCompanyContext $companyContext, private AuditRecorder $audit) {}

    public function handle(int $accountId, UpdateAccountB2BPolicyData $data): Account
    {
        $companyId = $this->companyId();

        return DB::transaction(function () use ($companyId, $accountId, $data): Account {
            $account = Account::query()->where('company_id', $companyId)->lockForUpdate()->findOrFail($accountId);
            if ($data->defaultWarehouseId !== null && ! Warehouse::query()
                ->where('company_id', $companyId)
                ->whereKey($data->defaultWarehouseId)
                ->where('is_active', true)
                ->exists()) {
                throw ValidationException::withMessages(['default_warehouse_id' => 'Aktif şirkete ait aktif bir depo seçilmelidir.']);
            }

            $policy = AccountB2BPolicy::query()
                ->where('company_id', $companyId)
                ->where('account_id', $accountId)
                ->first();
            $before = $this->snapshot($policy);

            if (! $policy instanceof AccountB2BPolicy) {
                $policy = new AccountB2BPolicy(['company_id' => $companyId, 'account_id' => $accountId]);
            }

            $policy->fill([
                'is_enabled' => $data->isEnabled,
                'allow_orders' => $data->allowOrders,
                'show_price' => $data->showPrice,
                'show_stock' => $data->showStock,
                'show_balance' => $data->showBalance,
                'show_invoices' => $data->showInvoices,
                'show_statement' => $data->showStatement,
                'allow_address_management' => $data->allowAddressManagement,
                'default_warehouse_id' => $data->defaultWarehouseId,
                'risk_behavior' => $data->riskBehavior,
            ]);
            $policy->save();

            $this->audit->record(
                AuditAction::AccountB2BPolicyUpdated,
                AuditTargetType::Account,
                $account->getKey(),
                before: $before,
                after: $this->snapshot($policy),
            );
            $account->setRelation('b2bPolicy', $policy);

            return $account;
        });
    }

    /** @return array<string, mixed> */
    private function snapshot(?AccountB2BPolicy $policy): array
    {
        if ($policy === null) {
            return [
                'is_enabled' => false, 'allow_orders' => false, 'show_price' => false, 'show_stock' => false,
                'show_balance' => false, 'show_invoices' => false, 'show_statement' => false,
                'allow_address_management' => false, 'default_warehouse_id' => null, 'risk_behavior' => 'block',
            ];
        }

        $riskBehavior = B2BRiskBehavior::tryFrom((string) $policy->getRawOriginal('risk_behavior'))
            ?? throw new LogicException('Persisted B2B risk behavior is invalid.');

        return [
            'is_enabled' => (bool) $policy->is_enabled,
            'allow_orders' => (bool) $policy->allow_orders,
            'show_price' => (bool) $policy->show_price,
            'show_stock' => (bool) $policy->show_stock,
            'show_balance' => (bool) $policy->show_balance,
            'show_invoices' => (bool) $policy->show_invoices,
            'show_statement' => (bool) $policy->show_statement,
            'allow_address_management' => (bool) $policy->allow_address_management,
            'default_warehouse_id' => $policy->default_warehouse_id === null ? null : (int) $policy->default_warehouse_id,
            'risk_behavior' => $riskBehavior->value,
        ];
    }

    private function companyId(): int
    {
        $companyId = $this->companyContext->requireCompany()->getKey();
        if (! is_int($companyId)) {
            throw new LogicException('Account B2B policy update requires a persisted active company.');
        }

        return $companyId;
    }
}
