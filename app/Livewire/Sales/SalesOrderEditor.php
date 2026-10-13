<?php

namespace App\Livewire\Sales;

use App\Actions\Documents\SaveSalesDocumentDraft;
use App\Actions\Documents\SourceLineAvailability;
use App\Actions\Sales\CancelSalesOrderRemaining;
use App\Actions\Sales\ConfirmSalesOrder;
use App\Actions\Sales\CreateDispatchFromOrder;
use App\Actions\Sales\CreateInvoiceFromOrder;
use App\Actions\Sales\IssueProforma;
use App\Actions\Sales\ReserveSalesOrderLines;
use App\Enums\DocumentType;
use App\Models\Period\StockReservation;

class SalesOrderEditor extends BaseSalesDocumentEditor
{
    public bool $riskAccepted = false;

    /** @var array<int,int|null> */
    public array $reservationLocationIds = [];

    /** @var array<int,string> */
    public array $fulfillmentQuantities = [];

    /** @var array<int,int|null> */
    public array $fulfillmentLocationIds = [];

    /** @var array<int,string> Confirmed, still-active stock allocations in base units. */
    public array $reservedQuantities = [];

    public string $reservationNotice = '';

    public string $reservationNoticeLevel = 'info';

    protected function documentType(): DocumentType
    {
        return DocumentType::SalesOrder;
    }

    protected function permissionPrefix(): string
    {
        return 'sales_orders';
    }

    protected function pageTitle(): string
    {
        return $this->document ? 'Satış Siparişi' : 'Yeni Satış Siparişi';
    }

    protected function extraMutationNames(): array
    {
        return ['confirm', 'reserve', 'cancelRemaining', 'dispatch', 'invoice', 'proforma'];
    }

    public function mount(?int $id = null): void
    {
        parent::mount($id);
        $this->syncFulfillmentInputs();
    }

    public function save(SaveSalesDocumentDraft $action): void
    {
        parent::save($action);
        $this->syncFulfillmentInputs();
    }

    public function confirm(ConfirmSalesOrder $action): void
    {
        abort_unless($this->document !== null, 422);
        $this->loadDocument($action->handle(
            $this->document,
            $this->mutationKey('confirm'),
            $this->riskAccepted,
        )->load('lines'));
        $this->completeMutation('confirm');
        $this->syncFulfillmentInputs();
    }

    public function reserve(ReserveSalesOrderLines $action): void
    {
        abort_unless($this->document !== null && $this->document->status === 'confirmed', 422);

        $this->reservationNotice = '';
        $this->reservationNoticeLevel = 'info';
        $priorities = [];

        // The fulfillment location is unrelated to the reservation location.
        // Only validated stock lines in this order may be reserved.
        foreach ($this->document->lines as $line) {
            if ($line->line_kind !== 'stock') {
                continue;
            }

            $locationId = $this->reservationLocationIds[$line->id] ?? null;
            if ($locationId) {
                $priorities[(int) $line->id] = [(int) $locationId];
            }
        }

        if ($priorities === []) {
            $this->reservationNotice = 'Rezervasyon için en az bir stok satırında rezervasyon deposu seçin.';

            return;
        }

        $results = $action->handle($this->document, $priorities, $this->mutationKey('reserve'));
        $this->completeMutation('reserve');
        $this->syncFulfillmentInputs();

        $reserved = '0.000';
        $unfilled = '0.000';
        foreach ($results as $result) {
            $reserved = bcadd($reserved, (string) $result['reserved_quantity'], 3);
            $unfilled = bcadd($unfilled, (string) $result['open_quantity'], 3);
        }

        if ($results === []) {
            $this->reservationNotice = 'Ayrılacak yeni miktar yok. Mevcut rezervasyonları ve siparişin kalan miktarını kontrol edin.';
        } elseif (bccomp($reserved, '0', 3) === 0) {
            $this->reservationNotice = 'Seçilen depolarda kullanılabilir stok bulunamadı. Başka rezervasyon deposu seçin.';
        } else {
            $this->reservationNoticeLevel = 'success';
            $this->reservationNotice = $reserved.' temel birim stok rezerve edildi.'
                .(bccomp($unfilled, '0', 3) > 0 ? ' Karşılanamayan miktar: '.$unfilled.'.' : '');
        }
    }

    public function cancelRemaining(CancelSalesOrderRemaining $action): void
    {
        abort_unless($this->document !== null, 422);
        $this->loadDocument($action->handle(
            $this->document,
            $this->mutationKey('cancelRemaining'),
        )->load('lines'));
        $this->completeMutation('cancelRemaining');
        $this->syncFulfillmentInputs();
    }

    public function createDispatch(CreateDispatchFromOrder $action): mixed
    {
        abort_unless($this->document !== null, 422);
        $dispatch = $action->handle(
            $this->document,
            $this->selectedQuantities(),
            $this->selectedLocations(),
            $this->documentDate,
            $this->mutationKey('dispatch'),
        );
        $this->completeMutation('dispatch');

        return $this->redirectRoute('sales.dispatches.edit', ['id' => $dispatch->id], navigate: false);
    }

    public function createInvoice(CreateInvoiceFromOrder $action): mixed
    {
        abort_unless($this->document !== null, 422);
        $invoice = $action->handle(
            $this->document,
            $this->selectedQuantities(),
            $this->selectedLocations(),
            $this->documentDate,
            $this->mutationKey('invoice'),
        );
        $this->completeMutation('invoice');

        return $this->redirectRoute('sales.invoices.edit', ['id' => $invoice->id], navigate: false);
    }

    public function issueProforma(IssueProforma $action): mixed
    {
        abort_unless($this->document !== null, 422);
        $proforma = $action->handle(
            $this->document,
            $this->documentDate,
            $this->mutationKey('proforma'),
        );
        $this->completeMutation('proforma');

        return $this->redirectRoute('sales.proformas.show', ['id' => $proforma->id], navigate: false);
    }

    private function syncFulfillmentInputs(): void
    {
        if (! $this->document) {
            return;
        }

        $this->document->load('lines');
        $availability = app(SourceLineAvailability::class);

        foreach ($this->document->lines as $line) {
            $this->fulfillmentQuantities[$line->id] = $availability->orderRemaining($line);
            $this->fulfillmentLocationIds[$line->id] ??= $line->location_id;
            $this->reservationLocationIds[$line->id] ??= $line->location_id;
        }

        // Never infer reservation success from the click. Read current active
        // records so reloading the page shows the same actual quantities.
        $this->reservedQuantities = [];
        StockReservation::query()
            ->where('document_type', DocumentType::SalesOrder->value)
            ->where('document_id', $this->document->id)
            ->where('status', 'active')
            ->get(['document_line_id', 'quantity'])
            ->each(function (StockReservation $reservation): void {
                if ($reservation->document_line_id === null) {
                    return;
                }
                $id = (int) $reservation->document_line_id;
                $this->reservedQuantities[$id] = bcadd(
                    $this->reservedQuantities[$id] ?? '0.000',
                    (string) $reservation->quantity,
                    3,
                );
            });
    }

    /** @return array<int,string> */
    private function selectedQuantities(): array
    {
        return array_filter(
            $this->fulfillmentQuantities,
            fn (string $quantity): bool => bccomp($quantity, '0', 3) > 0,
        );
    }

    /** @return array<int,int> */
    private function selectedLocations(): array
    {
        $result = [];

        foreach ($this->fulfillmentLocationIds as $lineId => $locationId) {
            if ($locationId) {
                $result[(int) $lineId] = (int) $locationId;
            }
        }

        return $result;
    }
}
