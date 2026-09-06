<?php

namespace App\Modules\Accounts\Crm;

use App\Foundation\Features\FeatureKey;
use App\Foundation\Features\FeatureRegistry;
use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AttachmentTargetType;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class CrmController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private FeatureRegistry $features,
        private CrmService $crm,
        private CrmFileManager $files,
        private AuditRecorder $audit,
    ) {}

    public function index(): View
    {
        $this->requireFeature();
        $companyId = $this->companyId();

        return view('accounts.crm.index', [
            'leads' => DB::table('crm_leads')->where('company_id', $companyId)->orderByDesc('id')->limit(100)->get(),
            'opportunities' => DB::table('crm_opportunities')->where('company_id', $companyId)->orderByDesc('id')->limit(100)->get(),
            'activities' => DB::table('crm_activities')->where('company_id', $companyId)->orderByRaw('due_at NULLS LAST')->orderByDesc('id')->limit(100)->get(),
            'accounts' => DB::table('accounts')->where('company_id', $companyId)->orderBy('legal_name')->limit(200)->get(['id', 'code', 'legal_name']),
            'owners' => DB::table('company_memberships')
                ->join('users', 'users.id', '=', 'company_memberships.user_id')
                ->where('company_memberships.company_id', $companyId)
                ->where('company_memberships.is_active', true)
                ->where('users.status', 'active')
                ->orderBy('users.name')
                ->get(['users.id', 'users.name']),
        ]);
    }

    public function storeLead(Request $request): RedirectResponse
    {
        $this->requireFeature();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'company_name' => ['nullable', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:64'],
            'owner_user_id' => ['nullable', 'integer'],
        ]);

        $id = DB::transaction(function () use ($validated): int {
            $id = $this->crm->createLead($this->companyId(), $validated);
            $this->audit->record(
                AuditAction::CrmLeadCreated,
                AuditTargetType::CrmLead,
                $id,
                after: ['name' => (string) $validated['name'], 'owner_user_id' => $validated['owner_user_id'] ?? null],
            );

            return $id;
        });

        return redirect()->route('crm.index')->with('status', 'Lead oluşturuldu: #'.$id);
    }

    public function storeOpportunity(Request $request): RedirectResponse
    {
        $this->requireFeature();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'lead_id' => ['nullable', 'integer'],
            'account_id' => ['nullable', 'integer'],
            'owner_user_id' => ['nullable', 'integer'],
            'expected_value' => ['nullable', 'numeric', 'min:0'],
            'currency_code' => ['nullable', 'string', 'size:3'],
            'expected_close_date' => ['nullable', 'date'],
        ]);

        $id = DB::transaction(function () use ($validated): int {
            $id = $this->crm->createOpportunity($this->companyId(), $validated);
            $this->audit->record(
                AuditAction::CrmOpportunityCreated,
                AuditTargetType::CrmOpportunity,
                $id,
                after: [
                    'name' => (string) $validated['name'],
                    'lead_id' => $validated['lead_id'] ?? null,
                    'account_id' => $validated['account_id'] ?? null,
                    'owner_user_id' => $validated['owner_user_id'] ?? null,
                    'stage' => 'new',
                ],
            );

            return $id;
        });

        return redirect()->route('crm.index')->with('status', 'Fırsat oluşturuldu: #'.$id);
    }

    public function moveStage(Request $request, int $opportunity): RedirectResponse
    {
        $this->requireFeature();
        $validated = $request->validate([
            'stage' => ['required', 'string', 'in:new,qualified,proposal,won,lost,cancelled'],
        ]);
        $companyId = $this->companyId();

        DB::transaction(function () use ($companyId, $opportunity, $validated): void {
            $before = DB::table('crm_opportunities')->where('company_id', $companyId)->where('id', $opportunity)->first();
            abort_if($before === null, 404);

            $this->crm->moveOpportunityStage($companyId, $opportunity, (string) $validated['stage'], $this->actorId());
            $this->audit->record(
                AuditAction::CrmOpportunityStageChanged,
                AuditTargetType::CrmOpportunity,
                $opportunity,
                before: ['stage' => (string) $before->stage],
                after: ['stage' => (string) $validated['stage']],
            );
        });

        return redirect()->route('crm.index')->with('status', 'Fırsat aşaması güncellendi.');
    }

    public function convertLead(Request $request, int $lead): RedirectResponse
    {
        $this->requireFeature();
        $validated = $request->validate(['account_id' => ['required', 'integer']]);
        $companyId = $this->companyId();
        $accountId = (int) $validated['account_id'];

        DB::transaction(function () use ($companyId, $lead, $accountId): void {
            $before = DB::table('crm_leads')->where('company_id', $companyId)->where('id', $lead)->first();
            abort_if($before === null, 404);

            $this->crm->convertLeadToAccount($companyId, $lead, $accountId);
            $this->audit->record(
                AuditAction::CrmLeadConverted,
                AuditTargetType::CrmLead,
                $lead,
                before: ['converted_account_id' => $before->converted_account_id],
                after: ['converted_account_id' => $accountId],
            );
        });

        return redirect()->route('crm.index')->with('status', 'Lead cariye bağlandı.');
    }

    public function storeActivity(Request $request): RedirectResponse
    {
        $this->requireFeature();
        $validated = $request->validate([
            'lead_id' => ['nullable', 'integer'],
            'opportunity_id' => ['nullable', 'integer'],
            'activity_type' => ['required', 'string', 'max:64'],
            'subject' => ['required', 'string', 'max:191'],
            'owner_user_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:10000'],
            'due_at' => ['nullable', 'date'],
        ]);

        $id = DB::transaction(function () use ($validated): int {
            $id = $this->crm->addActivity(
                $this->companyId(),
                isset($validated['lead_id']) ? (int) $validated['lead_id'] : null,
                isset($validated['opportunity_id']) ? (int) $validated['opportunity_id'] : null,
                (string) $validated['activity_type'],
                (string) $validated['subject'],
                isset($validated['owner_user_id']) ? (int) $validated['owner_user_id'] : null,
                isset($validated['note']) ? (string) $validated['note'] : null,
                isset($validated['due_at']) ? (string) $validated['due_at'] : null,
            );
            $this->audit->record(
                AuditAction::CrmActivityCreated,
                AuditTargetType::CrmActivity,
                $id,
                after: [
                    'lead_id' => $validated['lead_id'] ?? null,
                    'opportunity_id' => $validated['opportunity_id'] ?? null,
                    'activity_type' => (string) $validated['activity_type'],
                    'subject' => (string) $validated['subject'],
                    'due_at' => $validated['due_at'] ?? null,
                ],
            );

            return $id;
        });

        return redirect()->route('crm.index')->with('status', 'CRM aktivitesi oluşturuldu: #'.$id);
    }

    public function updateCommercialLinks(Request $request, int $opportunity): RedirectResponse
    {
        $this->requireFeature();
        $validated = $request->validate([
            'account_id' => ['nullable', 'integer'],
            'quote_id' => ['nullable', 'integer'],
            'sales_order_id' => ['nullable', 'integer'],
        ]);
        $companyId = $this->companyId();

        DB::transaction(function () use ($companyId, $opportunity, $validated): void {
            $before = DB::table('crm_opportunities')->where('company_id', $companyId)->where('id', $opportunity)->first();
            abort_if($before === null, 404);

            $accountId = isset($validated['account_id']) ? (int) $validated['account_id'] : null;
            $quoteId = isset($validated['quote_id']) ? (int) $validated['quote_id'] : null;
            $salesOrderId = isset($validated['sales_order_id']) ? (int) $validated['sales_order_id'] : null;
            $this->crm->linkCommercialRecords($companyId, $opportunity, $accountId, $quoteId, $salesOrderId);
            $this->audit->record(
                AuditAction::CrmCommercialLinksUpdated,
                AuditTargetType::CrmOpportunity,
                $opportunity,
                before: [
                    'account_id' => $before->account_id,
                    'quote_id' => $before->quote_id,
                    'sales_order_id' => $before->sales_order_id,
                ],
                after: [
                    'account_id' => $accountId,
                    'quote_id' => $quoteId,
                    'sales_order_id' => $salesOrderId,
                ],
            );
        });

        return redirect()->route('crm.index')->with('status', 'Ticari bağlantılar güncellendi.');
    }

    public function uploadLeadFile(Request $request, int $lead): RedirectResponse
    {
        $this->requireFeature();
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'label' => ['nullable', 'string', 'max:160'],
        ]);
        $upload = $request->file('file');
        if ($upload === null) {
            throw new LogicException('Validated CRM upload is missing.');
        }
        $this->files->upload(AttachmentTargetType::CrmLead, $lead, $upload, isset($validated['label']) ? (string) $validated['label'] : null);

        return redirect()->route('crm.index')->with('status', 'Lead dosyası yüklendi.');
    }

    public function downloadLeadFile(int $lead, int $attachment): StreamedResponse
    {
        $this->requireFeature();

        return $this->files->download(AttachmentTargetType::CrmLead, $lead, $attachment);
    }

    public function uploadOpportunityFile(Request $request, int $opportunity): RedirectResponse
    {
        $this->requireFeature();
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'label' => ['nullable', 'string', 'max:160'],
        ]);
        $upload = $request->file('file');
        if ($upload === null) {
            throw new LogicException('Validated CRM upload is missing.');
        }
        $this->files->upload(AttachmentTargetType::CrmOpportunity, $opportunity, $upload, isset($validated['label']) ? (string) $validated['label'] : null);

        return redirect()->route('crm.index')->with('status', 'Fırsat dosyası yüklendi.');
    }

    public function downloadOpportunityFile(int $opportunity, int $attachment): StreamedResponse
    {
        $this->requireFeature();

        return $this->files->download(AttachmentTargetType::CrmOpportunity, $opportunity, $attachment);
    }

    private function requireFeature(): void
    {
        abort_unless($this->features->enabled(FeatureKey::LightCrm), 404);
    }

    private function companyId(): int
    {
        $id = $this->companyContext->requireCompany()->getKey();

        return is_int($id) ? $id : throw new LogicException('CRM operation requires a persisted company.');
    }

    private function actorId(): int
    {
        $id = Auth::guard('web')->id();

        return is_int($id) ? $id : throw new LogicException('CRM operation requires an authenticated actor.');
    }
}
