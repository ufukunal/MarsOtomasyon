<?php

namespace App\Modules\Accounts\Crm;

use DomainException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CrmService
{
    /** @param array{name:string,company_name?:?string,email?:?string,phone?:?string,owner_user_id?:?int} $data */
    public function createLead(int $companyId, array $data): int
    {
        $name = trim($data['name']);
        if ($name === '') {
            throw new DomainException('Lead name is required.');
        }

        return (int) DB::table('crm_leads')->insertGetId([
            'company_id' => $companyId,
            'owner_user_id' => $data['owner_user_id'] ?? null,
            'name' => mb_substr($name, 0, 191),
            'company_name' => isset($data['company_name']) ? mb_substr((string) $data['company_name'], 0, 191) : null,
            'email' => isset($data['email']) ? mb_substr((string) $data['email'], 0, 191) : null,
            'phone' => isset($data['phone']) ? mb_substr((string) $data['phone'], 0, 64) : null,
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @param array{name:string,lead_id?:?int,account_id?:?int,owner_user_id?:?int,expected_value?:?string,currency_code?:?string,expected_close_date?:?string} $data */
    public function createOpportunity(int $companyId, array $data): int
    {
        $name = trim($data['name']);
        if ($name === '') {
            throw new DomainException('Opportunity name is required.');
        }
        $leadId = $data['lead_id'] ?? null;
        $accountId = $data['account_id'] ?? null;
        if ($leadId !== null && ! DB::table('crm_leads')->where('company_id', $companyId)->where('id', $leadId)->exists()) {
            throw new DomainException('Lead not found for company.');
        }
        if ($accountId !== null && ! DB::table('accounts')->where('company_id', $companyId)->where('id', $accountId)->exists()) {
            throw new DomainException('Account not found for company.');
        }

        return DB::transaction(function () use ($companyId, $data, $name, $leadId, $accountId): int {
            $id = (int) DB::table('crm_opportunities')->insertGetId([
                'company_id' => $companyId,
                'lead_id' => $leadId,
                'account_id' => $accountId,
                'owner_user_id' => $data['owner_user_id'] ?? null,
                'name' => mb_substr($name, 0, 191),
                'stage' => 'new',
                'expected_value' => $data['expected_value'] ?? null,
                'currency_code' => isset($data['currency_code']) ? strtoupper((string) $data['currency_code']) : null,
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
                'changed_by_user_id' => $data['owner_user_id'] ?? null,
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
        if ($stage === '') {
            throw new DomainException('Opportunity stage is required.');
        }

        DB::transaction(function () use ($companyId, $opportunityId, $stage, $actorUserId): void {
            $opportunity = DB::table('crm_opportunities')->where('company_id', $companyId)->where('id', $opportunityId)->lockForUpdate()->first();
            if ($opportunity === null) {
                throw new DomainException('Opportunity not found for company.');
            }
            $from = (string) $opportunity->stage;
            if ($from === $stage) {
                return;
            }
            DB::table('crm_opportunities')->where('id', $opportunityId)->update(['stage' => $stage, 'updated_at' => now()]);
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

    public function convertLeadToAccount(int $companyId, int $leadId, int $accountId): int
    {
        if (! DB::table('accounts')->where('company_id', $companyId)->where('id', $accountId)->exists()) {
            throw new DomainException('Account not found for company.');
        }

        return DB::transaction(function () use ($companyId, $leadId, $accountId): int {
            $lead = DB::table('crm_leads')->where('company_id', $companyId)->where('id', $leadId)->lockForUpdate()->first();
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
            DB::table('crm_opportunities')->where('company_id', $companyId)->where('lead_id', $leadId)->whereNull('account_id')->update([
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
        if ($leadId !== null && ! DB::table('crm_leads')->where('company_id', $companyId)->where('id', $leadId)->exists()) {
            throw new DomainException('Lead not found for company.');
        }
        if ($opportunityId !== null && ! DB::table('crm_opportunities')->where('company_id', $companyId)->where('id', $opportunityId)->exists()) {
            throw new DomainException('Opportunity not found for company.');
        }
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
}
