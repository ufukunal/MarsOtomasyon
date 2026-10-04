<?php

namespace App\Livewire\Sales;

use App\Actions\Sales\StartVehicleHotSale;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period\Contact;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Models\Period\Unit;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class VehicleHotSale extends Component
{
    use WithIdempotentMutations;

    public ?int $vehicleLocationId = null;
    public ?int $contactId = null;
    public string $documentDate = '';

    /** @var list<array<string,mixed>> */
    public array $lines = [];

    public function mount(): void
    {
        $this->seedMutationKeys(['post']);
        abort_unless(auth()->user()?->can('sales_invoices.create'), 403);
        $this->documentDate = now()->toDateString();
        $this->addLine();
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'line_kind' => 'stock',
            'product_id' => null,
            'unit_id' => null,
            'quantity' => '1.000',
            'unit_price' => '',
            'line_discount_rate' => '0',
            'line_discount_amount' => '0',
            'vat_rate' => '20',
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function post(StartVehicleHotSale $action): mixed
    {
        $invoice = $action->handle(
            (int) $this->vehicleLocationId,
            [
                'contact_id' => $this->contactId,
                'document_date' => $this->documentDate,
                'discount_rate' => '0',
            ],
            $this->lines,
            $this->mutationKey('post'),
        );
        $this->completeMutation('post');

        return $this->redirectRoute('sales.invoices.edit', ['id' => $invoice->id], navigate: false);
    }

    public function render(): View
    {
        return view('livewire.sales.vehicle-hot-sale', [
            'vehicles' => Location::query()->where('kind', 'vehicle')->where('is_active', true)->orderBy('name')->get(),
            'contacts' => Contact::query()->where('is_active', true)->orderBy('title')->limit(500)->get(),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->limit(500)->get(),
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(),
        ])->layout('layouts.app', ['pageTitle' => 'Araç Sıcak Satış']);
    }
}
