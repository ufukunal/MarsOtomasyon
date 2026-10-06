<?php

namespace App\Livewire\Pages\Settings;

use App\Actions\Periods\CopyPeriodAccess;
use App\Actions\Periods\PreviewPeriodCarry;
use App\Jobs\CarryPeriodJob;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period;
use App\Support\Period\PeriodContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

final class PeriodCarry extends Component
{
    use WithIdempotentMutations;

    public ?int $sourcePeriodId = null;

    public int $targetYear;

    /** @var array<string,mixed>|null */
    public ?array $preview = null;

    public ?int $completedTargetPeriodId = null;

    /** @var list<int|string> */
    public array $selectedAccessUserIds = [];

    public function mount(): void
    {
        Gate::authorize('periods.view');
        $this->seedMutationKeys(['carry', 'copyAccess']);

        $companyId = (int) PeriodContext::companyId();

        $source = Period::query()
            ->where('company_id', $companyId)
            ->where('id', auth()->user()?->last_period_id)
            ->where('status', 'active')
            ->first()
            ?? Period::query()
                ->where('company_id', $companyId)
                ->where('status', 'active')
                ->orderByDesc('year')
                ->first();

        $this->sourcePeriodId = $source?->id ? (int) $source->id : null;
        $this->targetYear = $source ? ((int) $source->year + 1) : (now()->year + 1);
    }

    public function updatedSourcePeriodId(): void
    {
        $source = $this->sourcePeriodId
            ? Period::query()
                ->where('company_id', PeriodContext::companyId())
                ->find($this->sourcePeriodId)
            : null;

        if ($source) {
            $this->targetYear = (int) $source->year + 1;
        }

        $this->preview = null;
        $this->completedTargetPeriodId = null;
        $this->selectedAccessUserIds = [];
    }

    public function previewCarry(PreviewPeriodCarry $action): void
    {
        Gate::authorize('periods.create');

        $validated = $this->validate([
            'sourcePeriodId' => ['required', 'integer', 'exists:master.periods,id'],
            'targetYear' => ['required', 'integer', 'between:2000,2200'],
        ]);

        $source = Period::query()
            ->where('company_id', PeriodContext::companyId())
            ->findOrFail((int) $validated['sourcePeriodId']);
        $this->preview = $action->handle($source, (int) $validated['targetYear'])->toArray();
    }

    public function carry(): void
    {
        $validated = $this->validate([
            'sourcePeriodId' => ['required', 'integer', 'exists:master.periods,id'],
            'targetYear' => ['required', 'integer', 'between:2000,2200'],
        ]);

        Gate::authorize('periods.update');

        $source = Period::query()
            ->where('company_id', PeriodContext::companyId())
            ->findOrFail((int) $validated['sourcePeriodId']);
        $actorId = auth()->id();

        if (! $actorId) {
            abort(403);
        }

        CarryPeriodJob::dispatch(
            (int) $actorId,
            (int) $source->id,
            (int) $validated['targetYear'],
            $this->mutationKey('carry'),
        );

        $this->preview = null;
        $this->selectedAccessUserIds = [];

        session()->flash(
            'success',
            'Dönem devri privileged operations queueya alındı. Ekran tamamlanma durumunu otomatik izleyecek.',
        );
    }

    public function refreshCarryStatus(): void
    {
        if (! $this->sourcePeriodId) {
            return;
        }

        $target = Period::query()
            ->where('company_id', PeriodContext::companyId())
            ->where('carried_from_period_id', $this->sourcePeriodId)
            ->where('year', $this->targetYear)
            ->whereNotNull('carried_at')
            ->first();

        if (! $target) {
            return;
        }

        $this->completedTargetPeriodId = (int) $target->id;
        $this->completeMutation('carry');
    }

    public function copyAccess(CopyPeriodAccess $action): void
    {
        if (! $this->sourcePeriodId || ! $this->completedTargetPeriodId) {
            return;
        }

        $companyId = (int) PeriodContext::companyId();
        $source = Period::query()
            ->where('company_id', $companyId)
            ->findOrFail($this->sourcePeriodId);
        $target = Period::query()
            ->where('company_id', $companyId)
            ->findOrFail($this->completedTargetPeriodId);
        $userIds = array_values(array_unique(array_map('intval', $this->selectedAccessUserIds)));

        $this->runMasterMutation(
            'copyAccess',
            fn (): array => $action->handle($source, $target, $userIds),
        );

        session()->flash('success', 'Seçili kullanıcı dönem erişimleri ve permission override kayıtları kopyalandı.');
    }

    public function render(): View
    {
        $sourcePeriods = Period::query()
            ->with('company')
            ->where('company_id', PeriodContext::companyId())
            ->whereIn('status', ['active', 'closed'])
            ->orderBy('company_id')
            ->orderByDesc('year')
            ->get()
            ->filter(function (Period $period): bool {
                $userId = auth()->id();

                return $userId !== null && DB::connection('master')
                    ->table('period_user_access')
                    ->where('period_id', $period->id)
                    ->where('user_id', $userId)
                    ->where('is_active', true)
                    ->exists();
            })
            ->values();

        $accessCandidates = collect();

        if ($this->sourcePeriodId && $this->completedTargetPeriodId) {
            $accessCandidates = DB::connection('master')
                ->table('period_user_access')
                ->join('users', 'users.id', '=', 'period_user_access.user_id')
                ->where('period_user_access.period_id', $this->sourcePeriodId)
                ->where('period_user_access.is_active', true)
                ->where('users.is_active', true)
                ->orderBy('users.name')
                ->get([
                    'users.id',
                    'users.name',
                    'users.email',
                    'period_user_access.permission_overrides',
                ]);
        }

        return view('livewire.pages.settings.period-carry', [
            'sourcePeriods' => $sourcePeriods,
            'accessCandidates' => $accessCandidates,
        ])->layout('layouts.app', [
            'pageTitle' => 'Dönem Devri',
            'pageDescription' => 'Kaynak kapanışını yeni yıl period DB açılışına kontrollü ve izlenebilir biçimde taşıyın.',
        ]);
    }
}
