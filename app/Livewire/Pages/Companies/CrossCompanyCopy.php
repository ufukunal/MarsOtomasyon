<?php

namespace App\Livewire\Pages\Companies;

use App\Actions\Companies\CopyRecordsBetweenCompanies;
use App\Actions\Companies\InspectCopiedRecords;
use App\Enums\CompanyCopyPermissionType;
use App\Models\Company;
use App\Models\CompanyCopyPermission;
use App\Models\Period\Contact;
use App\Models\Period\Product;
use App\Support\Period\PeriodContext;
use App\Support\Period\SourcePeriodContext;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class CrossCompanyCopy extends Component
{
    public ?int $sourceCompanyId = null;

    public string $type = 'contact';

    /** @var list<int> */
    public array $selected = [];

    /** @var list<array<string, mixed>> */
    public array $sourceRows = [];

    /** @var list<array<string, mixed>> */
    public array $conflicts = [];

    /** @var array<int, array<string, mixed>> */
    public array $choices = [];

    /** @var list<string> */
    public array $warnings = [];

    /** @var array<string, mixed> */
    public array $result = [];

    /** @var list<array<string, mixed>> */
    public array $sourceChanges = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('company_copy_permissions.view'), 403);
    }

    public function loadSource(): void
    {
        $this->selected = [];
        $this->conflicts = [];
        $this->choices = [];
        $this->warnings = [];
        $this->result = [];
        $this->sourceChanges = [];

        if (! $this->sourceCompanyId) {
            $this->sourceRows = [];

            return;
        }

        $type = CompanyCopyPermissionType::from($this->type);

        abort_unless(
            CompanyCopyPermission::allows(
                $this->sourceCompanyId,
                (int) PeriodContext::companyId(),
                $type,
            ),
            403,
        );

        SourcePeriodContext::use($this->sourceCompanyId);

        try {
            $rows = $type === CompanyCopyPermissionType::Contact
                ? Contact::on('period_source')->orderBy('code')->limit(500)->get(['id', 'code', 'title'])
                : Product::on('period_source')->orderBy('code')->limit(500)->get(['id', 'code', 'name']);

            $this->sourceRows = $rows->map(fn ($row) => [
                'id' => (int) $row->id,
                'code' => (string) $row->code,
                'label' => (string) ($row->title ?? $row->name),
            ])->all();
        } finally {
            SourcePeriodContext::clear();
        }
    }

    public function copy(CopyRecordsBetweenCompanies $action): void
    {
        $result = $action->handle(
            (int) $this->sourceCompanyId,
            CompanyCopyPermissionType::from($this->type),
            array_map('intval', $this->selected),
            $this->choices,
        );

        $this->result = $result->toArray();
        $this->conflicts = $this->result['conflicts'];
        $this->warnings = $this->result['warnings'];
    }

    public function inspectSourceChanges(InspectCopiedRecords $action): void
    {
        abort_unless($this->sourceCompanyId !== null, 422);

        $this->sourceChanges = $action->inspect(
            (int) $this->sourceCompanyId,
            CompanyCopyPermissionType::from($this->type),
        );
    }

    public function refreshFromSource(int $targetId, InspectCopiedRecords $action): void
    {
        abort_unless($this->sourceCompanyId !== null, 422);

        $action->refresh(
            (int) $this->sourceCompanyId,
            CompanyCopyPermissionType::from($this->type),
            $targetId,
        );

        $this->inspectSourceChanges($action);
        session()->flash('warning', 'Seçilen kart kaynak verisiyle kullanıcı onayıyla güncellendi.');
    }

    public function render(): View
    {
        $targetCompanyId = PeriodContext::companyId();

        $sourceCompanyIds = CompanyCopyPermission::query()
            ->where('target_company_id', $targetCompanyId)
            ->where('type', $this->type)
            ->where('is_active', true)
            ->pluck('source_company_id');

        return view('livewire.pages.companies.cross-company-copy', [
            'sourceCompanies' => Company::query()->whereIn('id', $sourceCompanyIds)->orderBy('name')->get(),
        ])->layout('layouts.app', ['pageTitle' => 'Başka Şirketten Aktar']);
    }
}
