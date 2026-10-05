<?php

namespace App\Livewire\Imports;

use App\Actions\Imports\CarryImportFileFromPeriod;
use App\Actions\Imports\CloseImportFile;
use App\Actions\Imports\RecalculateImportCosts;
use App\Actions\Imports\ReceiveImportFile;
use App\Actions\Imports\SaveImportContainer;
use App\Actions\Imports\SaveImportCostItem;
use App\Actions\Imports\SaveImportFile;
use App\Actions\Imports\SaveImportPackage;
use App\Enums\ProductKind;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period;
use App\Models\Period\Contact;
use App\Models\Period\ImportContainer;
use App\Models\Period\ImportCostItem;
use App\Models\Period\ImportFile;
use App\Models\Period\ImportPackage;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Queries\Imports\BuildContainerProfitability;
use App\Queries\Imports\BuildImportFileProfitability;
use App\Support\Period\PeriodContext;
use App\Support\Period\SourcePeriodContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class ImportCenter extends Component
{
    use WithIdempotentMutations;

    public ?int $selectedFileId = null;
    public ?int $fileVersion = null;
    public ?int $supplierContactId = null;
    public ?int $receivingLocationId = null;
    public string $country = '';
    public string $incoterm = 'FOB';
    public string $currency = 'USD';
    public string $exchangeRate = '';
    public ?string $etd = null;
    public ?string $eta = null;
    public string $fileStatus = 'draft';
    public string $fileNotes = '';

    public ?int $containerEditId = null;
    public string $containerNo = '';
    public string $containerType = '40 HC';
    public string $sealNo = '';
    public string $containerWeight = '';
    public string $containerVolume = '';
    public ?string $containerEtd = null;
    public ?string $containerEta = null;
    public string $containerStatus = 'planned';

    public ?int $packageEditId = null;
    public ?int $packageContainerId = null;
    public string $cartonNo = '';
    public string $componentName = '';
    public ?int $packageProductId = null;
    public ?int $packageLocationId = null;
    public string $packageQuantity = '1.000';
    public string $packageUnitPrice = '0.0000';
    public string $packageWeight = '';
    public string $packageVolume = '';

    public ?int $costEditId = null;
    public string $costName = '';
    public string $costAmount = '0.0000';
    public string $costCurrency = 'TRY';
    public string $costExchangeRate = '';
    public string $costBasis = 'value';

    public string $receivingDate = '';
    public ?int $carrySourcePeriodId = null;
    public ?int $carrySourceImportFileId = null;

    public function mount(): void
    {
        $this->seedMutationKeys(['file', 'container', 'package', 'cost', 'allocate', 'receive', 'close', 'carry']);
        abort_unless(auth()->user()?->can('import_shipments.view'), 403);
        $this->receivingDate = now()->toDateString();
    }

    public function updatedCarrySourcePeriodId(): void
    {
        $this->carrySourceImportFileId = null;
    }

    public function newFile(): void
    {
        $this->selectedFileId = null;
        $this->fileVersion = null;
        $this->supplierContactId = null;
        $this->receivingLocationId = null;
        $this->country = '';
        $this->incoterm = 'FOB';
        $this->currency = 'USD';
        $this->exchangeRate = '';
        $this->etd = null;
        $this->eta = null;
        $this->fileStatus = 'draft';
        $this->fileNotes = '';
        $this->resetChildForms();
    }

    public function selectFile(int $id): void
    {
        $file = ImportFile::query()->findOrFail($id);
        $this->selectedFileId = (int) $file->id;
        $this->fileVersion = (int) $file->version;
        $this->supplierContactId = (int) $file->supplier_contact_id;
        $this->receivingLocationId = $file->receiving_location_id;
        $this->country = (string) ($file->country ?? '');
        $this->incoterm = (string) ($file->incoterm ?? '');
        $this->currency = (string) $file->currency;
        $this->exchangeRate = (string) ($file->exchange_rate ?? '');
        $this->etd = $file->etd?->toDateString();
        $this->eta = $file->eta?->toDateString();
        $this->fileStatus = (string) $file->status;
        $this->fileNotes = (string) ($file->notes ?? '');
        $this->resetChildForms();
    }

    public function saveFile(SaveImportFile $action): void
    {
        $existing = $this->selectedFileId
            ? ImportFile::query()->findOrFail($this->selectedFileId)
            : null;

        $saved = $this->runPeriodMutation('file', fn () => $action->handle([
            'supplier_contact_id' => $this->supplierContactId,
            'receiving_location_id' => $this->receivingLocationId,
            'country' => $this->country,
            'incoterm' => $this->incoterm,
            'currency' => $this->currency,
            'exchange_rate' => $this->exchangeRate !== '' ? $this->exchangeRate : null,
            'etd' => $this->etd,
            'eta' => $this->eta,
            'status' => $this->fileStatus,
            'notes' => $this->fileNotes,
        ], $existing, $this->fileVersion));

        $this->selectFile((int) $saved->id);
    }

    public function editContainer(int $id): void
    {
        $container = ImportContainer::query()->findOrFail($id);
        abort_unless((int) $container->import_file_id === $this->selectedFileId, 404);
        $this->containerEditId = (int) $container->id;
        $this->containerNo = (string) $container->container_no;
        $this->containerType = (string) ($container->container_type ?? '');
        $this->sealNo = (string) ($container->seal_no ?? '');
        $this->containerWeight = (string) ($container->gross_weight_kg ?? '');
        $this->containerVolume = (string) ($container->volume_cbm ?? '');
        $this->containerEtd = $container->etd?->toDateString();
        $this->containerEta = $container->eta?->toDateString();
        $this->containerStatus = (string) $container->status;
    }

    public function saveContainer(SaveImportContainer $action): void
    {
        $file = $this->currentFile();
        $container = $this->containerEditId
            ? ImportContainer::query()->findOrFail($this->containerEditId)
            : null;

        $this->runPeriodMutation('container', fn () => $action->handle($file, [
            'container_no' => $this->containerNo,
            'container_type' => $this->containerType,
            'seal_no' => $this->sealNo,
            'gross_weight_kg' => $this->containerWeight,
            'volume_cbm' => $this->containerVolume,
            'etd' => $this->containerEtd,
            'eta' => $this->containerEta,
            'status' => $this->containerStatus,
        ], $container));

        $this->resetContainerForm();
    }

    public function editPackage(int $id): void
    {
        $package = ImportPackage::query()->findOrFail($id);
        abort_unless((int) $package->import_file_id === $this->selectedFileId, 404);
        $this->packageEditId = (int) $package->id;
        $this->packageContainerId = (int) $package->container_id;
        $this->cartonNo = (string) $package->carton_no;
        $this->componentName = (string) ($package->component_name ?? '');
        $this->packageProductId = $package->product_id;
        $this->packageLocationId = $package->location_id;
        $this->packageQuantity = (string) $package->quantity;
        $this->packageUnitPrice = (string) $package->unit_price;
        $this->packageWeight = (string) ($package->weight_kg ?? '');
        $this->packageVolume = (string) ($package->volume_cbm ?? '');
    }

    public function savePackage(SaveImportPackage $action): void
    {
        $file = $this->currentFile();
        $package = $this->packageEditId
            ? ImportPackage::query()->findOrFail($this->packageEditId)
            : null;

        $this->runPeriodMutation('package', fn () => $action->handle($file, [
            'container_id' => $this->packageContainerId,
            'carton_no' => $this->cartonNo,
            'component_name' => $this->componentName,
            'product_id' => $this->packageProductId,
            'location_id' => $this->packageLocationId,
            'quantity' => $this->packageQuantity,
            'unit_price' => $this->packageUnitPrice,
            'weight_kg' => $this->packageWeight,
            'volume_cbm' => $this->packageVolume,
        ], $package));

        $this->resetPackageForm();
    }

    public function editCost(int $id): void
    {
        $item = ImportCostItem::query()->findOrFail($id);
        abort_unless((int) $item->import_file_id === $this->selectedFileId, 404);
        $this->costEditId = (int) $item->id;
        $this->costName = (string) $item->name;
        $this->costAmount = (string) $item->amount;
        $this->costCurrency = (string) $item->currency;
        $this->costExchangeRate = (string) ($item->exchange_rate ?? '');
        $this->costBasis = (string) $item->allocation_basis;
    }

    public function saveCost(SaveImportCostItem $action): void
    {
        $file = $this->currentFile();
        $item = $this->costEditId
            ? ImportCostItem::query()->findOrFail($this->costEditId)
            : null;

        $this->runPeriodMutation('cost', fn () => $action->handle($file, [
            'name' => $this->costName,
            'amount' => $this->costAmount,
            'currency' => $this->costCurrency,
            'exchange_rate' => $this->costExchangeRate,
            'allocation_basis' => $this->costBasis,
        ], $item));

        $this->resetCostForm();
    }

    public function recalculate(RecalculateImportCosts $action): void
    {
        $action->handle($this->currentFile(), $this->mutationKey('allocate'));
        $this->completeMutation('allocate');
    }

    public function receive(ReceiveImportFile $action): void
    {
        $received = $action->handle(
            $this->currentFile(),
            $this->receivingDate,
            $this->mutationKey('receive'),
        );
        $this->completeMutation('receive');
        $this->selectFile((int) $received->id);
    }

    public function close(CloseImportFile $action): void
    {
        $closed = $action->handle($this->currentFile(), $this->mutationKey('close'));
        $this->completeMutation('close');
        $this->selectFile((int) $closed->id);
    }

    public function carryFromPeriod(CarryImportFileFromPeriod $action): void
    {
        abort_unless($this->carrySourcePeriodId !== null && $this->carrySourceImportFileId !== null, 422);

        $sourcePeriod = Period::query()
            ->where('company_id', PeriodContext::companyId())
            ->findOrFail($this->carrySourcePeriodId);

        $carried = $action->handle(
            $sourcePeriod,
            $this->carrySourceImportFileId,
            $this->mutationKey('carry'),
        );

        $this->completeMutation('carry');
        $this->carrySourcePeriodId = null;
        $this->carrySourceImportFileId = null;
        $this->selectFile((int) $carried->id);
    }

    public function render(
        BuildImportFileProfitability $fileProfitability,
        BuildContainerProfitability $containerProfitability,
    ): View {
        $file = $this->selectedFileId
            ? ImportFile::query()
                ->with(['supplier', 'receivingLocation', 'containers', 'packages.product', 'packages.location', 'packages.container', 'costItems'])
                ->find($this->selectedFileId)
            : null;
        $sourcePeriods = $this->carrySourcePeriods();
        $selectedSourcePeriod = $this->carrySourcePeriodId
            ? $sourcePeriods->firstWhere('id', $this->carrySourcePeriodId)
            : null;

        return view('livewire.imports.import-center', [
            'files' => ImportFile::query()->with('supplier')->orderByDesc('id')->limit(200)->get(),
            'file' => $file,
            'contacts' => Contact::query()->where('is_active', true)->orderBy('title')->limit(500)->get(),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(),
            'products' => Product::query()
                ->where('is_active', true)
                ->where('kind', '!=', ProductKind::Set->value)
                ->orderBy('name')
                ->limit(1000)
                ->get(),
            'fileProfitability' => $file && in_array($file->status, ['received', 'closed'], true)
                ? $fileProfitability->handle($file)
                : null,
            'containerProfitability' => $file && in_array($file->status, ['received', 'closed'], true)
                ? $containerProfitability->handle($file)
                : [],
            'sourcePeriods' => $sourcePeriods,
            'carrySourceFiles' => $this->carrySourceFiles($selectedSourcePeriod),
        ])->layout('layouts.app', ['pageTitle' => 'İthalat Merkezi']);
    }

    /** @return Collection<int, Period> */
    private function carrySourcePeriods(): Collection
    {
        $userId = auth()->id();

        if (! auth()->user()?->can('import_shipments.create')
            || $userId === null
            || PeriodContext::companyId() === null
            || PeriodContext::year() === null) {
            return collect();
        }

        $accessiblePeriodIds = DB::connection('master')
            ->table('period_user_access')
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->pluck('period_id');

        return Period::query()
            ->where('company_id', PeriodContext::companyId())
            ->whereIn('id', $accessiblePeriodIds)
            ->where('year', '<', PeriodContext::year())
            ->where('status', 'closed')
            ->orderByDesc('year')
            ->get();
    }

    /** @return Collection<int, object> */
    private function carrySourceFiles(?Period $period): Collection
    {
        if ($period === null) {
            return collect();
        }

        SourcePeriodContext::usePeriod($period);

        try {
            return DB::connection('period_source')
                ->table('import_files')
                ->whereIn('status', ['draft', 'in_transit', 'customs'])
                ->orderByDesc('id')
                ->limit(200)
                ->get(['id', 'number', 'status', 'eta']);
        } finally {
            SourcePeriodContext::clear();
        }
    }

    private function currentFile(): ImportFile
    {
        abort_unless($this->selectedFileId !== null, 422);

        return ImportFile::query()->findOrFail($this->selectedFileId);
    }

    private function resetChildForms(): void
    {
        $this->resetContainerForm();
        $this->resetPackageForm();
        $this->resetCostForm();
    }

    private function resetContainerForm(): void
    {
        $this->containerEditId = null;
        $this->containerNo = '';
        $this->containerType = '40 HC';
        $this->sealNo = '';
        $this->containerWeight = '';
        $this->containerVolume = '';
        $this->containerEtd = null;
        $this->containerEta = null;
        $this->containerStatus = 'planned';
    }

    private function resetPackageForm(): void
    {
        $this->packageEditId = null;
        $this->packageContainerId = null;
        $this->cartonNo = '';
        $this->componentName = '';
        $this->packageProductId = null;
        $this->packageLocationId = $this->receivingLocationId;
        $this->packageQuantity = '1.000';
        $this->packageUnitPrice = '0.0000';
        $this->packageWeight = '';
        $this->packageVolume = '';
    }

    private function resetCostForm(): void
    {
        $this->costEditId = null;
        $this->costName = '';
        $this->costAmount = '0.0000';
        $this->costCurrency = 'TRY';
        $this->costExchangeRate = '';
        $this->costBasis = 'value';
    }
}
