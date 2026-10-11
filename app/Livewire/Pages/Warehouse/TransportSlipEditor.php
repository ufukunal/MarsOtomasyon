<?php

namespace App\Livewire\Pages\Warehouse;

use App\Enums\DocumentType;
use App\Models\Period\Contact;
use App\Models\Period\Document;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Models\Period\TransportSlip;
use App\Models\Period\TransportSlipItem;
use App\Support\Period\PeriodContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class TransportSlipEditor extends Component
{
    public ?TransportSlip $slip = null;

    #[Locked]
    public string $mode = 'new';

    public ?string $preview = null;

    public bool $previewPrint = false;

    public string $productSearch = '';

    public string $carrierOption = '';

    public string $date = '';

    public ?int $contactId = null;

    public ?int $locationId = null;

    public string $orderReference = '';

    public string $dispatchReference = '';

    public string $shippingType = 'Ambar';

    public string $carrier = '';

    public string $destinationWarehouse = '';

    public int $packageCount = 1;

    public string $weightKg = '';

    public string $trackingNumber = '';

    public bool $containsGlass = false;

    public bool $fragile = false;

    public bool $keepUpright = false;

    public bool $doNotStack = false;

    public string $deliveryCompany = '';

    public string $deliveryPerson = '';

    public string $deliveryPhone = '';

    public string $deliveryCity = '';

    public string $deliveryDistrict = '';

    public string $deliveryAddress = '';

    public string $note = '';

    /** @var list<array{product_id:?int,quantity:string}> */
    public array $items = [];

    public function mount(?int $id = null): void
    {
        abort_unless(auth()->user()->can('warehouse_slips.view'), 403);
        $this->date = now()->toDateString();

        if ($id !== null) {
            $this->mode = request()->routeIs('warehouse.transport.edit') ? 'edit' : 'detail';
            if ($this->mode === 'edit') {
                abort_unless(auth()->user()->can('warehouse_slips.update'), 403);
            }
            $this->slip = TransportSlip::query()->with('items')->findOrFail($id);
            $this->loadSlip();
            if ($this->mode === 'edit') {
                abort_unless(in_array($this->slip->status, ['draft', 'ready'], true), 409);
            }

            return;
        }

        abort_unless(auth()->user()->can('warehouse_slips.create'), 403);
        $this->addItem();
    }

    public function updatedContactId(): void
    {
        $contact = $this->contactId ? Contact::query()->find($this->contactId) : null;

        if ($contact) {
            $this->deliveryCompany = $this->deliveryCompany ?: (string) $contact->title;
            $this->deliveryPerson = $this->deliveryPerson ?: (string) $contact->primary_contact_name;
            $this->deliveryPhone = $this->deliveryPhone ?: (string) ($contact->phone ?? '');
            $this->deliveryCity = $this->deliveryCity ?: (string) ($contact->city ?? '');
            $this->deliveryDistrict = $this->deliveryDistrict ?: (string) ($contact->district ?? '');
            $this->deliveryAddress = $this->deliveryAddress ?: (string) ($contact->address ?? '');
        }
    }

    public function updatedCarrierOption(string $value): void
    {
        if ($value !== '__other__') {
            $this->carrier = $value;
            $this->updatedCarrier();
        } else {
            $this->carrier = '';
        }
    }

    public function updatedCarrier(): void
    {
        if ($this->destinationWarehouse === '') {
            $this->destinationWarehouse = $this->carrier;
        }
    }

    public function addItem(): void
    {
        $this->assertEditable();
        $this->items[] = ['product_id' => null, 'quantity' => '1'];
    }

    public function removeItem(int $index): void
    {
        $this->assertEditable();

        if (count($this->items) <= 1) {
            $this->addError('items', 'En az bir ürün satırı kalmalıdır.');

            return;
        }

        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function save(): mixed
    {
        $this->assertEditable();
        PeriodContext::ensureWritable();

        $data = $this->validate($this->rules());

        $created = $this->slip === null;

        $this->slip = DB::connection('period')->transaction(function () use ($data, $created): TransportSlip {
            $slip = $created ? new TransportSlip : TransportSlip::query()->lockForUpdate()->findOrFail($this->slip->id);

            if (! $created && ! in_array($slip->status, ['draft', 'ready'], true)) {
                abort(409, 'Yola çıkarılan veya tamamlanan ambar fişi değiştirilemez.');
            }

            $slip->fill([
                'slip_date' => $data['date'],
                'contact_id' => $data['contactId'],
                'location_id' => $data['locationId'],
                'order_reference' => $data['orderReference'],
                'dispatch_reference' => $data['dispatchReference'],
                'shipping_type' => $data['shippingType'],
                'carrier' => $data['carrier'],
                'destination_warehouse' => $data['destinationWarehouse'],
                'package_count' => $data['packageCount'],
                'weight_kg' => $data['weightKg'] !== '' ? $data['weightKg'] : null,
                'tracking_number' => $data['trackingNumber'],
                'contains_glass' => $data['containsGlass'],
                'fragile' => $data['fragile'],
                'keep_upright' => $data['keepUpright'],
                'do_not_stack' => $data['doNotStack'],
                'delivery_company' => $data['deliveryCompany'],
                'delivery_person' => $data['deliveryPerson'],
                'delivery_phone' => $data['deliveryPhone'],
                'delivery_city' => $data['deliveryCity'],
                'delivery_district' => $data['deliveryDistrict'],
                'delivery_address' => $data['deliveryAddress'],
                'note' => $data['note'],
            ]);

            if ($created) {
                $slip->number = 'PENDING-'.str_replace('-', '', (string) Str::uuid());
                $slip->status = 'draft';
                $slip->created_by = auth()->id();
            }

            $slip->save();

            if ($created) {
                // The period-local primary key guarantees concurrency-safe numbers.
                $slip->number = sprintf('AF-%d-%05d', (int) substr($data['date'], 0, 4), $slip->id);
                $slip->save();
            }

            $slip->items()->delete();

            foreach ($data['items'] as $item) {
                $slip->items()->create([
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (string) $item['quantity'],
                ]);
            }

            // Deliberately no RecordStockMovement; transport paperwork is not G-207 adjustment.
            return $slip->refresh();
        }, 3);

        return $this->redirectRoute('warehouse.transport.show', ['id' => $this->slip->id], navigate: false);
    }

    public function advance(): void
    {
        abort_unless($this->slip !== null && auth()->user()->can('warehouse_slips.update'), 403);
        PeriodContext::ensureWritable();

        DB::connection('period')->transaction(function (): void {
            $slip = TransportSlip::query()->lockForUpdate()->findOrFail($this->slip->id);
            $next = match ($slip->status) {
                'draft' => 'ready',
                'ready' => 'handed_over',
                'handed_over' => 'completed',
                default => null,
            };
            abort_unless($next !== null, 409);
            $slip->status = $next;
            $slip->save();
        });

        $this->slip->refresh();
    }

    public function cancel(): void
    {
        abort_unless($this->slip !== null && auth()->user()->can('warehouse_slips.cancel'), 403);
        PeriodContext::ensureWritable();
        DB::connection('period')->transaction(function (): void {
            $slip = TransportSlip::query()->lockForUpdate()->findOrFail($this->slip->id);
            abort_unless(in_array($slip->status, ['draft', 'ready', 'handed_over'], true), 409);
            $slip->status = 'cancelled';
            $slip->save();
        });
        $this->slip->refresh();
    }

    public function openPreview(string $format, bool $print = false): void
    {
        abort_unless(auth()->user()->can('warehouse_slips.view'), 403);
        abort_unless(in_array($format, ['slip', 'labels'], true), 404);
        if ($this->mode !== 'detail') {
            $this->validate($this->rules());
        }

        $this->preview = $format;
        $this->previewPrint = $print;
    }

    public function closePreview(): void
    {
        $this->preview = null;
        $this->previewPrint = false;
    }

    public function duplicate(): mixed
    {
        abort_unless($this->slip !== null && auth()->user()->can('warehouse_slips.create'), 403);
        PeriodContext::ensureWritable();
        $copy = DB::connection('period')->transaction(function (): TransportSlip {
            $original = TransportSlip::query()->with('items')->findOrFail($this->slip->id);
            $duplicate = $original->replicate(['id', 'number', 'status', 'created_by', 'created_at', 'updated_at']);
            $duplicate->number = 'PENDING-'.str_replace('-', '', (string) Str::uuid());
            $duplicate->status = 'draft';
            $duplicate->created_by = auth()->id();
            $duplicate->slip_date = now()->toDateString();
            $duplicate->tracking_number = null;
            $duplicate->save();
            $duplicate->number = sprintf('AF-%d-%05d', now()->year, $duplicate->id);
            $duplicate->save();
            foreach ($original->items as $item) {
                $duplicate->items()->create([
                    'product_id' => $item->product_id,
                    'quantity' => (string) $item->quantity,
                ]);
            }

            return $duplicate;
        }, 3);

        return $this->redirectRoute('warehouse.transport.edit', ['id' => $copy->id], navigate: false);
    }

    public function render(): View
    {
        abort_unless(auth()->user()->can('warehouse_slips.view'), 403);

        return view('livewire.pages.warehouse.transport-slip-editor', [
            'contacts' => Contact::query()->where('is_active', true)->orderBy('title')->get(['id', 'code', 'title']),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'orderOptions' => Document::query()->where('document_type', DocumentType::SalesOrder)
                ->when($this->contactId, fn ($query) => $query->where('contact_id', $this->contactId))
                ->orderByDesc('id')->limit(250)->get(['id', 'number']),
            'dispatchOptions' => Document::query()->where('document_type', DocumentType::Dispatch)
                ->when($this->contactId, fn ($query) => $query->where('contact_id', $this->contactId))
                ->orderByDesc('id')->limit(250)->get(['id', 'number']),
            'carrierOptions' => TransportSlip::query()->where('carrier', '!=', '')
                ->distinct()->orderBy('carrier')->pluck('carrier'),
            'previewHtml' => $this->preview && $this->mode !== 'detail'
                ? $this->draftPrintHtml($this->preview) : null,
            'products' => Product::query()->where('is_active', true)->with('unit')
                ->when(trim($this->productSearch) !== '', function ($query): void {
                    $needle = '%'.trim($this->productSearch).'%';
                    $query->where(function ($products) use ($needle): void {
                        $products->where('code', 'ilike', $needle)
                            ->orWhere('name', 'ilike', $needle);
                    });
                })
                ->orderBy('name')->limit(80)->get()
                ->merge(Product::query()->with('unit')
                    ->whereKey(collect($this->items)->pluck('product_id')->filter()->all())->get())
                ->unique('id')->values(),
            'readonly' => $this->mode === 'detail' || ($this->slip !== null
                && (! in_array($this->slip->status, ['draft', 'ready'], true)
                    || ! auth()->user()->can('warehouse_slips.update'))),
            'statusLabels' => [
                'draft' => 'Taslak', 'ready' => 'Hazır', 'handed_over' => 'Ambara Teslim',
                'completed' => 'Tamamlandı', 'cancelled' => 'İptal',
            ],
        ])->layout('layouts.app', [
            'pageTitle' => $this->mode === 'new' ? 'Yeni Ambar Fişi'
                : ($this->mode === 'edit' ? 'Ambar Fişini Düzenle · ' : 'Ambar Fişi · ').$this->slip->number,
        ]);
    }

    /** @return array<string, list<mixed>> */
    private function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'contactId' => ['required', 'integer', Rule::exists('period.contacts', 'id')],
            'locationId' => ['nullable', 'integer', Rule::exists('period.locations', 'id')],
            'orderReference' => ['nullable', 'string', 'max:80'],
            'dispatchReference' => ['nullable', 'string', 'max:80'],
            'shippingType' => ['required', Rule::in(['Ambar', 'Nakliye', 'Kargo', 'Elden Teslim'])],
            'carrier' => ['required', 'string', 'max:180'],
            'destinationWarehouse' => ['required', 'string', 'max:180'],
            'packageCount' => ['required', 'integer', 'min:1', 'max:500'],
            'weightKg' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'trackingNumber' => ['nullable', 'string', 'max:120'],
            'containsGlass' => ['boolean'],
            'fragile' => ['boolean'],
            'keepUpright' => ['boolean'],
            'doNotStack' => ['boolean'],
            'deliveryCompany' => ['required', 'string', 'max:180'],
            'deliveryPerson' => ['nullable', 'string', 'max:120'],
            'deliveryPhone' => ['required', 'string', 'max:40'],
            'deliveryCity' => ['required', 'string', 'max:100'],
            'deliveryDistrict' => ['required', 'string', 'max:100'],
            'deliveryAddress' => ['required', 'string', 'max:4000'],
            'note' => ['nullable', 'string', 'max:4000'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.product_id' => ['required', 'integer', Rule::exists('period.products', 'id')],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999.999'],

        ];
    }

    private function draftPrintHtml(string $format): string
    {
        $draft = new TransportSlip([
            'number' => $this->slip !== null ? $this->slip->number : 'TASLAK', 'slip_date' => $this->date,
            'contact_id' => $this->contactId, 'shipping_type' => $this->shippingType,
            'carrier' => $this->carrier, 'destination_warehouse' => $this->destinationWarehouse,
            'package_count' => $this->packageCount, 'weight_kg' => $this->weightKg,
            'tracking_number' => $this->trackingNumber,
            'contains_glass' => $this->containsGlass, 'fragile' => $this->fragile,
            'keep_upright' => $this->keepUpright, 'do_not_stack' => $this->doNotStack,
            'order_reference' => $this->orderReference, 'dispatch_reference' => $this->dispatchReference,
            'delivery_company' => $this->deliveryCompany, 'delivery_person' => $this->deliveryPerson,
            'delivery_phone' => $this->deliveryPhone, 'delivery_city' => $this->deliveryCity,
            'delivery_district' => $this->deliveryDistrict, 'delivery_address' => $this->deliveryAddress,
            'note' => $this->note,
        ]);

        $draft->setRelation('contact', $this->contactId ? Contact::query()->find($this->contactId) : null);
        $productIds = collect($this->items)->pluck('product_id')->filter()->map(fn ($id): int => (int) $id)->all();
        $products = Product::query()->with('unit')->whereKey($productIds)->get()->keyBy('id');
        $draft->setRelation('items', collect($this->items)->map(function (array $row) use ($products): TransportSlipItem {
            $item = new TransportSlipItem([
                'product_id' => $row['product_id'], 'quantity' => $row['quantity'],
            ]);
            $item->setRelation('product', $products->get((int) $row['product_id']));

            return $item;
        }));

        return view('printing.transport-slip', [
            'slip' => $draft, 'format' => $format, 'autoprint' => false,
            'warnings' => collect([
                $this->containsGlass ? 'CAM ÜRÜN' : null,
                $this->fragile ? 'KIRILABİLİR / HASSAS' : null,
                $this->keepUpright ? 'DİK TAŞIYIN' : null,
                $this->doNotStack ? 'ÜSTÜNE YÜK KOYMAYIN' : null,
            ])->filter()->implode(' · '),
        ])->render();
    }

    private function assertEditable(): void
    {
        abort_unless($this->mode !== 'detail', 403);
        abort_unless(auth()->user()->can($this->slip ? 'warehouse_slips.update' : 'warehouse_slips.create'), 403);
        abort_unless($this->slip === null || in_array($this->slip->status, ['draft', 'ready'], true), 409);
    }

    private function loadSlip(): void
    {
        $s = $this->slip;
        $this->date = CarbonImmutable::parse((string) $s->slip_date)->toDateString();
        $this->contactId = $s->contact_id;
        $this->locationId = $s->location_id;
        $this->orderReference = (string) ($s->order_reference ?? '');
        $this->dispatchReference = (string) ($s->dispatch_reference ?? '');
        $this->shippingType = $s->shipping_type;
        $this->carrier = $s->carrier;
        $this->carrierOption = $s->carrier;
        $this->destinationWarehouse = $s->destination_warehouse;
        $this->packageCount = (int) $s->package_count;
        $this->weightKg = (string) ($s->weight_kg ?? '');
        $this->trackingNumber = (string) ($s->tracking_number ?? '');
        $this->containsGlass = (bool) $s->contains_glass;
        $this->fragile = (bool) $s->fragile;
        $this->keepUpright = (bool) $s->keep_upright;
        $this->doNotStack = (bool) $s->do_not_stack;
        $this->deliveryCompany = $s->delivery_company;
        $this->deliveryPerson = (string) ($s->delivery_person ?? '');
        $this->deliveryPhone = $s->delivery_phone;
        $this->deliveryCity = $s->delivery_city;
        $this->deliveryDistrict = $s->delivery_district;
        $this->deliveryAddress = $s->delivery_address;
        $this->note = (string) ($s->note ?? '');
        $this->items = $s->items->map(fn ($item): array => [
            'product_id' => $item->product_id,
            'quantity' => (string) $item->quantity,
        ])->values()->all();
    }
}
