<?php

namespace App\Livewire\Reporting;

use App\Actions\Imports\SaveImportReportingAllocation;
use App\Support\Period\PeriodContext;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

final class ImportSourceCenter extends Component
{
    public string $sourceType = 'sales';

    public string $sourceId = '';

    public string $containerId = '';

    public string $quantity = '';

    public string $note = '';

    public string $voidReason = '';

    public ?string $message = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('reports.view')
            && auth()->user()?->can('import_shipments.view'), 403);
        PeriodContext::ensure();
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can('import_shipments.update'), 403);

        $this->validate([
            'sourceType' => 'required|in:sales,return,fire,scrap',
            'sourceId' => 'required|integer|min:1',
            'containerId' => 'required|integer|min:1',
            'quantity' => ['required', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'note' => 'required|string|min:8|max:2000',
        ]);

        try {
            app(SaveImportReportingAllocation::class)->handle([
                'source_type' => $this->sourceType,
                'source_id' => (int) $this->sourceId,
                'container_id' => (int) $this->containerId,
                'quantity' => $this->quantity,
                'note' => $this->note,
            ]);
            $this->reset('sourceId', 'quantity', 'note');
            $this->message = 'Kaynak eşleştirmesi kaydedildi. Fiziksel stok kaydı değiştirilmedi.';
        } catch (DomainException $exception) {
            $this->addError('sourceId', $exception->getMessage());
        }
    }

    public function voidAllocation(int $id): void
    {
        abort_unless(auth()->user()?->can('import_shipments.update'), 403);

        $this->validate(['voidReason' => 'required|string|min:8|max:2000']);

        try {
            app(SaveImportReportingAllocation::class)->void($id, $this->voidReason);
            $this->voidReason = '';
            $this->message = 'Eşleştirme gerekçesiyle iptal edildi. İşlem geçmişi korundu.';
        } catch (DomainException $exception) {
            $this->addError('voidReason', $exception->getMessage());
        }
    }

    public function render(): View
    {
        abort_unless(auth()->user()?->can('reports.view')
            && auth()->user()?->can('import_shipments.view'), 403);
        PeriodContext::ensure();

        $db = DB::connection('period');
        $containers = $db->table('containers as c')
            ->join('import_files as f', 'f.id', '=', 'c.import_file_id')
            ->whereIn('f.status', ['received', 'closed'])
            ->orderByDesc('c.id')->limit(150)
            ->get(['c.id', 'c.container_no', 'f.number AS import_number']);

        $sources = match ($this->sourceType) {
            'sales', 'return' => $db->table('document_lines as l')
                ->join('documents as d', 'd.id', '=', 'l.document_id')
                ->join('products as p', 'p.id', '=', 'l.product_id')
                ->where('l.line_kind', 'stock')
                ->where('d.document_type', $this->sourceType === 'sales' ? 'sales_invoice' : 'sales_return')
                ->where('d.status', 'posted')
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')->from('document_relations as r')
                        ->where('r.relation_type', 'reversal_of')
                        ->where(function ($sub): void {
                            $sub->whereColumn('r.source_document_id', 'd.id')
                                ->orWhereColumn('r.target_document_id', 'd.id');
                        });
                })
                ->orderByDesc('l.id')->limit(50)
                ->get(['l.id', 'd.number', 'd.document_date', 'p.code AS product_code',
                    'l.base_quantity', 'l.quantity']),
            'fire' => $db->table('production_consumptions as pc')
                ->join('production_completions as c', 'c.id', '=', 'pc.production_completion_id')
                ->join('products as p', 'p.id', '=', 'pc.component_product_id')
                ->where('pc.fire_quantity', '>', 0)
                ->whereNull('c.reversal_of_id')
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')->from('production_completions as reversal')
                        ->whereColumn('reversal.reversal_of_id', 'c.id');
                })
                ->orderByDesc('pc.id')->limit(50)
                ->get(['pc.id', 'c.completion_date AS document_date', 'p.code AS product_code',
                    'pc.fire_quantity AS quantity']),
            'scrap' => $db->table('stock_movements as sm')
                ->join('products as p', 'p.id', '=', 'sm.product_id')
                ->leftJoin('warehouse_slips as ws', function ($join): void {
                    $join->on('ws.id', '=', 'sm.document_id')->where('sm.document_type', '=', 'warehouse_slip');
                })
                ->where('sm.direction', 'out')
                ->where(function ($query): void {
                    $query->where('sm.reason', 'scrap')
                        ->orWhere(function ($sub): void {
                            $sub->where('sm.reason', 'adjustment')
                                ->where('sm.document_type', 'warehouse_slip')
                                ->where('ws.status', 'posted')
                                ->where('ws.direction', 'out')
                                ->where('ws.reason', 'scrap');
                        });
                })
                ->where(function ($query): void {
                    $query->whereNull('ws.id')->orWhere('ws.status', 'posted');
                })
                ->orderByDesc('sm.id')->limit(50)
                ->get(['sm.id', 'sm.movement_date AS document_date',
                    'sm.document_no AS number', 'p.code AS product_code', 'sm.quantity']),
            default => collect(),
        };

        $allocations = $db->table('import_reporting_allocations as a')
            ->join('containers as c', 'c.id', '=', 'a.container_id')
            ->join('import_files as f', 'f.id', '=', 'a.import_file_id')
            ->join('products as p', 'p.id', '=', 'a.product_id')
            ->whereNull('a.voided_at')
            ->orderByDesc('a.id')->limit(50)
            ->get(['a.id', 'a.source_type', 'a.source_id', 'a.event_date',
                'a.quantity', 'a.note', 'f.number AS import_number',
                'c.container_no', 'p.code AS product_code']);

        return view('livewire.reporting.import-source-center', compact('containers', 'sources', 'allocations'))
            ->layout('layouts.app', ['title' => 'İthalat Kaynak Eşleştirmeleri']);
    }
}
