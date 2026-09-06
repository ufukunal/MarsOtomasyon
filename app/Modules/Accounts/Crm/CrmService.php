<?php

namespace App\Modules\Accounts\Crm;

use DomainException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CrmService
{
    /** @var list<string> */
    private const STAGES = ['new', 'qualified', 'proposal', 'won', 'lost', 'cancelled'];

    /** @param array{name:string,company_name?:?string,email?:?string,phone?:?string,owner_user_id?:?int} $data */
    public function createLead(int $companyId, array $data): int
    {
        $name = trim($data['name']);
        if ($name === '') {
            throw new DomainException('Lead name is required.');
        }

        $ownerUserId = $data['owner_user_id'] ?? null;
        $this->assertOwner($companyId, $ownerUserId);

        return (int) DB::table('crm_leads')->insertGetId([
            'company_id' => $companyId,
            'owner_user_id' => $ownerUserId,
            'name' => mb_substr($name, 0, 191),
            'company_name' => isset($data['company_name']) ? mb_substr(trim((string) $data['company_name']), 0, 191) : null,
            'email' => isset($data['email']) ? mb_substr(trim((string) $data['email']), 0, 191) : null,
            'phone' => isset($data['phone']) ? mb_substr(trim((string) $data['phone']), 0, 64) : null,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array{name:string,lead_id?:?int,account_id?:?int,owner_user_id?:?int,quote_id?:?int,sales_order_id?:?int,expected_value?:?string,currency_code?:?string,expected_close_date?:?string} $data */
    public function createOpportunity(int $companyId, array $data): int
    {
        $name = trim($data['name']);
        if ($name === '') {
            throw new DomainException('Opportunity name is required.');
        }

        $leadId = $data['lead_id'] ?? null;
        $accountId = $data['account_id'] ?? null;
        $ownerUserId = $data['owner_user_id'] ?? null;
        $quoteId = $data['quote_id'] ?? null;
        $salesOrderId = $data['sales_order_id'] ?? null;

        $this->assertOwner($companyId, $ownerUserId);
        $this->assertCompanyRecord('crm_leads', $companyId, $leadId, 'Lead');
        $this->assertCompanyRecord('accounts', $companyId, $accountId, 'Account');
        $this->assertCompanyRecord('quotes', $companyId, $quoteId, 'Quote');
        $this->assertCompanyRecord('sales_orders', $companyId, $salesOrderId, 'Sales order');

        return DB::transaction(function () use ($companyId, $data, $name, $leadId, $accountId, $ownerUserId, $quoteId, $salesOrderId): int {
            $id = (int) DB::table('crm_opportunities')->insertGetId([
                'company_id' => $companyId,
                'lead_id' => $leadId,
                'account_id' => $accountId,
                'owner_user_id' => $ownerUserId,
                'quote_id' => $quoteId,
                'sales_order_id' => $salesOrderId,
                'name' => mb_substr($name, 0, 191),
                'stage' => 'new',
                'expected_value' => $data['expected_value'] ?? null,
                'currency_code' => isset($data['currency_code']) ? strtoupper(trim((string) $data['currency_code'])) : null,
                'expected_close_date' => $data['expected_close_date'] ?? null,
                'status' => 'open',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('crm_opportunity_stage_history')->insert([
                'company_id' => $companyId,
                'opportunity_id' => $id,
                'from_stage' => null,
                'to_stage' => 'new',
                'changed_by_user_id' => $ownerUserId,
                'changed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $id;
        });
    }

    public function moveOpportunityStage(int $companyId, int $opportunityId, string $stage, ?int $actorUserId = null): void
    {
        $stage = strtolower(trim($stage));
        if (! in_array($stage, self::STAGES, true)) {
            throw new DomainException('Opportunity stage is invalid.');
        }
        $this->assertOwner($companyId, $actorUserId);

        DB::transaction(function () use ($companyId, $opportunityId, $stage, $actorUserId): void {
            $opportunity = DB::table('crm_opportunities')
                ->where('company_id', $companyId)
                ->where('id', $opportunityId)
                ->lockForUpdate()
                ->first();
            if ($opportunity === null) {
                throw new DomainException('Opportunity not found for company.');
            }

            $from = (string) $opportunity->stage;
            if ($from === $stage) {
                return;
            }

            DB::table('crm_opportunities')->where('id', $opportunityId)->update([
                'stage' => $stage,
                'status' => in_array($stage, ['won', 'lost', 'cancelled'], true) ? 'closed' : 'open',
                'updated_at' => now(),
            ]);
            DB::table('crm_opportunity_stage_history')->insert([
                'company_id' => $companyId,
                'opportunity_id' => $opportunityId,
                'from_stage' => $from,
                'to_stage' => $stage,
                'changed_by_user_id' => $actorUserId,
                'changed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function linkCommercialRecords(int $companyId, int $opportunityId, ?int $accountId, ?int $quoteId, ?int $salesOrderId): void
    {
        $this->assertCompanyRecord('accounts', $companyId, $accountId, 'Account');
        $this->assertCompanyRecord('quotes', $companyId, $quoteId, 'Quote');
        $this->assertCompanyRecord('sales_orders', $companyId, $salesOrderId, 'Sales order');

        $updated = DB::table('crm_opportunities')
            ->where('company_id', $companyId)
            ->where('id', $opportunityId)
            ->update([
                'account_id' => $accountId,
                'quote_id' => $quoteId,
                'sales_order_id' => $salesOrderId,
                'updated_at' => now(),
            ]);
        if ($updated !== 1) {
            throw new DomainException('Opportunity not found for company.');
        }
    }

    public function convertLeadToAccount(int $companyId, int $leadId, int $accountId): int
    {
        $this->assertCompanyRecord('accounts', $companyId, $accountId, 'Account');

        return DB::transaction(function () use ($companyId, $leadId, $accountId): int {
            $lead = DB::table('crm_leads')
                ->where('company_id', $companyId)
                ->where('id', $leadId)
                ->lockForUpdate()
                ->first();
            if ($lead === null) {
                throw new DomainException('Lead not found for company.');
            }
            if ($lead->converted_account_id !== null) {
                if ((int) $lead->converted_account_id !== $accountId) {
                    throw new DomainException('Lead was already converted to another account.');
                }

                return $accountId;
            }

            DB::table('crm_leads')->where('id', $leadId)->update([
                'converted_account_id' => $accountId,
                'status' => 'converted',
                'converted_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('crm_opportunities')
                ->where('company_id', $companyId)
                ->where('lead_id', $leadId)
                ->whereNull('account_id')
                ->update([
                    'account_id' => $accountId,
                    'updated_at' => now(),
                ]);

            return $accountId;
        });
    }

    public function addActivity(int $companyId, ?int $leadId, ?int $opportunityId, string $type, string $subject, ?int $ownerUserId = null, ?string $note = null, ?string $dueAt = null): int
    {
        if ($leadId === null && $opportunityId === null) {
            throw new DomainException('CRM activity must target a lead or opportunity.');
        }

        $this->assertCompanyRecord('crm_leads', $companyId, $leadId, 'Lead');
        $this->assertCompanyRecord('crm_opportunities', $companyId, $opportunityId, 'Opportunity');
        $this->assertOwner($companyId, $ownerUserId);

        $type = trim($type);
        $subject = trim($subject);
        if ($type === '' || $subject === '') {
            throw new DomainException('CRM activity type and subject are required.');
        }

        $id = DB::table('crm_activities')->insertGetId([
            'company_id' => $companyId,
            'lead_id' => $leadId,
            'opportunity_id' => $opportunityId,
            'owner_user_id' => $ownerUserId,
            'activity_type' => mb_substr($type, 0, 64),
            'subject' => mb_substr($subject, 0, 191),
            'note' => $note,
            'due_at' => $dueAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if (! is_int($id) && ! is_numeric($id)) {
            throw new RuntimeException('CRM activity could not be persisted.');
        }

        return (int) $id;
    }

    /** @return list<int> */
    public function suggestAccounts(int $companyId, int $leadId): array
    {
        $lead = DB::table('crm_leads')->where('company_id', $companyId)->where('id', $leadId)->first();
        if ($lead === null) {
            throw new DomainException('Lead not found for company.');
        }

        $name = trim((string) ($lead->company_name ?: $lead->name));
        $query = DB::table('accounts')->where('company_id', $companyId);
        if ($name !== '') {
            $query->where(function (Builder $builder) use ($name): void {
                $like = '%'.$name.'%';
                $builder->whereRaw('legal_name ILIKE ?', [$like])->orWhereRaw('trade_name ILIKE ?', [$like]);
            });
        }

        return array_map('intval', $query->orderBy('id')->limit(10)->pluck('id')->all());
    }

    private function assertOwner(int $companyId, ?int $userId): void
    {
        if ($userId === null) {
            return;
        }

        $exists = DB::table('company_memberships')
            ->where('company_id', $companyId)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->exists();
        if (! $exists) {
            throw new DomainException('CRM owner must be an active user of the company.');
        }
    }

    private function assertCompanyRecord(string $table, int $companyId, ?int $id, string $label): void
    {
        if ($id === null) {
            return;
        }

        if (! DB::table($table)->where('company_id', $companyId)->where('id', $id)->exists()) {
            throw new DomainException($label.' not found for company.');
        }
    }
}
