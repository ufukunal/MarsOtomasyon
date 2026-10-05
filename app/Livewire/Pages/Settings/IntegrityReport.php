<?php

namespace App\Livewire\Pages\Settings;

use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\IntegrityReport as IntegrityReportModel;
use App\Support\Integrity\Checks\ContactBalanceCheck;
use App\Support\Integrity\Checks\CostIntegrityCheck;
use App\Support\Integrity\Checks\DocumentTotalCheck;
use App\Support\Integrity\Checks\FinanceIntegrityCheck;
use App\Support\Integrity\Checks\ImportIntegrityCheck;
use App\Support\Integrity\Checks\NumberSeriesCheck;
use App\Support\Integrity\Checks\PartialDocumentCheck;
use App\Support\Integrity\Checks\ProductionIntegrityCheck;
use App\Support\Integrity\Checks\PurchaseMatchCheck;
use App\Support\Integrity\Checks\RecipeIntegrityCheck;
use App\Support\Integrity\Checks\QuarantineBalanceCheck;
use App\Support\Integrity\Checks\ReservationBalanceCheck;
use App\Support\Integrity\Checks\ReturnIntegrityCheck;
use App\Support\Integrity\Checks\StockBalanceCheck;
use App\Support\Integrity\Checks\UnitIntegrityCheck;
use App\Support\Integrity\IntegrityRunner;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class IntegrityReport extends Component
{
    use WithIdempotentMutations;

    public ?string $selectedCheck = null;

    public function mount(): void
    {
        $this->seedMutationKeys(['runNow']);
        abort_unless(auth()->user()?->can('audit.view'), 403);
    }

    public function runNow(string $check, IntegrityRunner $runner): void
    {
        abort_unless(auth()->user()?->can('audit.view'), 403);

        $map = [
            'stock' => StockBalanceCheck::class,
            'costs' => CostIntegrityCheck::class,
            'reservations' => ReservationBalanceCheck::class,
            'quarantine' => QuarantineBalanceCheck::class,
            'units' => UnitIntegrityCheck::class,
            'documents' => DocumentTotalCheck::class,
            'partials' => PartialDocumentCheck::class,
            'purchases' => PurchaseMatchCheck::class,
            'contacts' => ContactBalanceCheck::class,
            'finance' => FinanceIntegrityCheck::class,
            'returns' => ReturnIntegrityCheck::class,
            'imports_phase7' => ImportIntegrityCheck::class,
            'recipes_phase8' => RecipeIntegrityCheck::class,
            'production_phase8' => ProductionIntegrityCheck::class,
            'numbers' => NumberSeriesCheck::class,
        ];

        abort_unless(isset($map[$check]), 404);

        $this->runPeriodMutation('runNow', fn () => $runner->run(app($map[$check])));

        $this->selectedCheck = $check;
    }

    public function render(): View
    {
        $reports = IntegrityReportModel::query()
            ->latest('run_at')
            ->limit(100)
            ->get();

        return view('livewire.pages.settings.integrity-report', [
            'reports' => $reports,
        ])->layout('layouts.app', [
            'pageTitle' => 'Bütünlük Kontrolü',
        ]);
    }
}
