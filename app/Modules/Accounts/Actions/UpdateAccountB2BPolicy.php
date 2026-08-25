<?php

namespace App\Modules\Accounts\Actions;

use App\Modules\Accounts\Models\Account;
use App\Modules\Accounts\Models\AccountB2BPolicy;
use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class UpdateAccountB2BPolicy
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private AuditRecorder $audit,
    ) {}

    public function handle(int $accountId, UpdateAccountB2BPolicyData $data): Account
    {
        $companyId = $this->companyId();

        return DB::transaction(function () use ($companyId, $accountId, $data): Account {
            $account = Account::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($accountId);

            $policy = AccountB2BPolicy::query()
                ->where('company_id', $companyId)
                ->where('account_id', $accountId)
                ->first();

            $before = $this->snapshot($policy);

            if (! $policy instanceof AccountB2BPolicy) {
                $policy = new AccountB2BPolicy([
                    'company_id' => $companyId,
                    'account_id' => $accountId,
                ]);
            }

            $policy->fill([
                'is_enabled' => $data->isEnabled,
                'allow_orders' => $data->allowOrders,
                'show_stock' => $data->showStock,
                'show_invoices' => $data->showInvoices,
                'show_statement' => $data->showStatement,
                'allow_address_management' => $data->allowAddressManagement,
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

    /** @return array<string, bool> */
    private function snapshot(?AccountB2BPolicy $policy): array
    {
        if ($policy === null) {
            return [
                'is_enabled' => false,
                'allow_orders' => false,
                'show_stock' => false,
                'show_invoices' => false,
                'show_statement' => false,
                'allow_address_management' => false,
            ];
        }

        return [
            'is_enabled' => (bool) $policy->is_enabled,
            'allow_orders' => (bool) $policy->allow_orders,
            'show_stock' => (bool) $policy->show_stock,
            'show_invoices' => (bool) $policy->show_invoices,
            'show_statement' => (bool) $policy->show_statement,
            'allow_address_management' => (bool) $policy->allow_address_management,
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
