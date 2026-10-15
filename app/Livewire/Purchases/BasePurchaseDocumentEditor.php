<?php

namespace App\Livewire\Purchases;

use App\Actions\Purchases\SavePurchaseDocumentDraft;
use App\Enums\DocumentType;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period\Contact;
use App\Models\Period\Document;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Models\Period\Unit;
use Illuminate\Contracts\View\View;
use Livewire\Component;

abstract class BasePurchaseDocumentEditor extends Component
{
    use WithIdempotentMutations;

    public ?Document $document = null;

    public ?int $contactId = null;

    public string $documentDate = '';

    public ?string $dueDate = null;

    public string $currency = 'TRY';

    public string $exchangeRate = '1.000000';

    public string $discountRate = '0';

    public string $bulkVatRate = '20';

    public string $notes = '';

    public int $version = 1;

    public bool $documentDirty = false;

    public string $contactSearch = '';

    /** @var array<string,string> */
    public array $productSearches = [];

    /** Cached per request to avoid repeating the same default options query for every line. */
    private ?\Illuminate\Database\Eloquent\Collection $defaultProductOptions = null;

    /** @var list<array<string,mixed>> */
    public array $lines = [];

    abstract protected function documentType(): DocumentType;

    abstract protected function permissionPrefix(): string;

    abstract protected function pageTitle(): string;

    /** @return list<string> */
    protected function extraMutationNames(): array
    {
        return [];
    }

    public function mount(?int $id = null): void
    {
        $this->seedMutationKeys(['save', ...$this->extraMutationNames()]);
        abort_unless(auth()->user()?->can($this->permissionPrefix().'.view'), 403);
        $this->documentDate = now()->toDateString();

        if ($id !== null) {
            $document = Document::query()->with('lines')->findOrFail($id);
            abort_unless($document->document_type === $this->documentType(), 404);
            $this->loadDocument($document);
        } else {
            $this->lines = [$this->emptyLine()];
        }
    }

    public function updated(string $property): void
    {
        // Changing between stock and service must not silently carry over
        // the previous product's description, unit, location or price.
        if (preg_match('/^lines\.(\d+)\.line_kind$/D', $property, $kindMatch)) {
            $index = (int) $kindMatch[1];
            if (isset($this->lines[$index])) {
                foreach (['product_id', 'unit_id', 'location_id', 'conversion_factor'] as $field) {
                    $this->lines[$index][$field] = null;
                }
                $this->lines[$index]['description'] = '';
                $this->lines[$index]['unit_price'] = '0';
                $this->lines[$index]['vat_rate'] = '0';
                $this->lines[$index]['line_discount_rate'] = '0';
                $this->lines[$index]['line_discount_amount'] = '0';
            }
        }

        // Apply the selected product's actual unit, VAT and description
        // on the server, so the row stays coherent after Livewire morphing.
        if (preg_match('/^lines\.(\d+)\.product_id$/D', $property, $productMatch)) {
            $index = (int) $productMatch[1];
            $productId = (int) ($this->lines[$index]['product_id'] ?? 0);
            $product = $productId > 0
                ? Product::query()->where('is_active', true)->find($productId)
                : null;

            if ($productId === 0) {
                // Clearing a product cannot leave the previous product's price
                // or stock metadata attached to an empty stock line.
                foreach (['unit_id', 'location_id', 'conversion_factor'] as $field) {
                    $this->lines[$index][$field] = null;
                }
                $this->lines[$index]['description'] = '';
                $this->lines[$index]['unit_price'] = '0';
                $this->lines[$index]['vat_rate'] = '0';
                $this->lines[$index]['line_discount_rate'] = '0';
                $this->lines[$index]['line_discount_amount'] = '0';
            }

            if ($product !== null) {
                $this->lines[$index]['unit_id'] = $product->unit_id;
                $this->lines[$index]['vat_rate'] = (string) $product->vat_rate;
                $this->lines[$index]['description'] = $product->name;
                $this->lines[$index]['conversion_factor'] = null;
                $this->lines[$index]['line_discount_rate'] = '0';
                $this->lines[$index]['line_discount_amount'] = '0';
                // The old product's purchase price cannot be reused for a new
                // product; purchase unit price must be entered deliberately.
                $this->lines[$index]['unit_price'] = '0';
            }
        }

        // When a source value changes, an old persisted discount amount cannot
        // be reused with the new price, quantity or discount percentage.
        if (preg_match('/^lines\.(\d+)\.(quantity|unit_price|line_discount_rate)$/D', $property, $matches)) {
            $index = (int) $matches[1];
            if (isset($this->lines[$index])) {
                $this->lines[$index]['line_discount_amount'] = '0';
            }
        }

        foreach (['contactId', 'documentDate', 'dueDate', 'validUntil', 'currency', 'exchangeRate', 'discountRate', 'notes', 'lines'] as $field) {
            if ($property === $field || str_starts_with($property, $field.'.')) {
                $this->documentDirty = true;

                return;
            }
        }
    }

    protected function requireSavedDraft(): void
    {
        if ($this->documentDirty) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'document' => 'Belgede kaydedilmemiş değişiklikler var. İşleme devam etmek için önce Kaydet düğmesini kullanın.',
            ]);
        }
    }

    public function addLine(): void
    {
        $this->lines[] = $this->emptyLine();
        $this->documentDirty = true;
    }

    public function removeLine(int $index): void
    {
        $rowKey = (string) ($this->lines[$index]['row_key'] ?? '');
        if ($rowKey !== '') {
            unset($this->productSearches[$rowKey]);
        }
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        $this->documentDirty = true;
    }

    public function applyVatToAll(): void
    {
        $this->validateOnly('bulkVatRate', [
            'bulkVatRate' => ['required', 'numeric', 'between:0,100'],
        ], [], ['bulkVatRate' => 'Toplu KDV oranı']);

        $rate = bcadd($this->bulkVatRate, '0', 4);
        abort_if(bccomp($rate, '0', 4) < 0 || bccomp($rate, '100', 4) > 0, 422);

        foreach ($this->lines as $index => $line) {
            $this->lines[$index]['vat_rate'] = $rate;
        }
        $this->documentDirty = true;
    }

    public function clearVat(): void
    {
        foreach ($this->lines as $index => $line) {
            $this->lines[$index]['vat_rate'] = '0.0000';
        }
        $this->documentDirty = true;
    }

    public function save(SavePurchaseDocumentDraft $action): void
    {
        abort_unless(auth()->user()?->can(
            $this->permissionPrefix().($this->document ? '.update' : '.create')
        ), 403);

        $this->validate([
            'contactId' => ['required', 'integer'],
            'documentDate' => ['required', 'date'],
            'dueDate' => ['nullable', 'date'],
            'currency' => ['required', 'string', 'size:3'],
            'exchangeRate' => ['required', 'numeric', 'gt:0'],
            'discountRate' => ['required', 'numeric', 'between:0,100'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_kind' => ['required', 'in:stock,service'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.line_discount_rate' => ['required', 'numeric', 'between:0,100'],
            'lines.*.vat_rate' => ['required', 'numeric', 'between:0,100'],
        ], [], [
            'contactId' => 'Tedarikçi', 'documentDate' => 'Belge tarihi',
            'dueDate' => 'Vade tarihi', 'currency' => 'Para birimi',
            'exchangeRate' => 'Kur', 'discountRate' => 'Belge iskonto oranı',
            'lines' => 'Belge satırları', 'lines.*.quantity' => 'Satır miktarı',
            'lines.*.unit_price' => 'Satır birim fiyatı',
            'lines.*.line_discount_rate' => 'Satır iskonto oranı',
            'lines.*.vat_rate' => 'Satır KDV oranı',
        ]);

        foreach ($this->lines as $index => $line) {
            $kind = (string) ($line['line_kind'] ?? 'stock');
            if ($kind === 'stock' && empty($line['product_id'])) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'lines.'.$index.'.product_id' => 'Stok satırı için ürün seçin.',
                ]);
            }
            if ($kind === 'service' && trim((string) ($line['description'] ?? '')) === '') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'lines.'.$index.'.description' => 'Hizmet açıklamasını girin.',
                ]);
            }
        }

        $saved = $this->runPeriodMutation('save', fn () => $action->handle(
            $this->documentType(),
            [
                'contact_id' => $this->contactId,
                'document_date' => $this->documentDate,
                'due_date' => $this->dueDate,
                'currency' => $this->currency,
                'exchange_rate' => $this->exchangeRate,
                'discount_rate' => $this->discountRate,
                'notes' => $this->notes,
            ],
            $this->lines,
            $this->document,
            $this->document ? $this->version : null,
        ));

        $this->loadDocument($saved->load('lines'));
        $this->documentDirty = false;
    }


    /**
     * Unsaved, non-authoritative display calculation. Final amounts always come
     * from Save*DocumentDraft and are revalidated inside the period transaction.
     */
    public function draftTotalsPreview(): ?\App\DataObjects\Documents\DocumentTotals
    {
        if ($this->document && $this->document->status !== 'draft') {
            return null;
        }

        if ($this->lines === []) {
            return null;
        }

        $input = [];
        foreach ($this->lines as $line) {
            $quantity = trim((string) ($line['quantity'] ?? ''));
            $price = trim((string) ($line['unit_price'] ?? ''));
            $vat = trim((string) ($line['vat_rate'] ?? '0'));
            $discount = trim((string) ($line['line_discount_rate'] ?? '0'));

            if ($quantity === '' || $price === '' || ! is_numeric($quantity)
                || ! is_numeric($price) || ! is_numeric($vat) || ! is_numeric($discount)) {
                return null;
            }

            $input[] = [
                'quantity' => $quantity,
                'unit_price' => $price,
                'line_discount_rate' => $discount,
                'line_discount_amount' => (string) ($line['line_discount_amount'] ?? '0'),
                'vat_rate' => $vat,
            ];
        }

        try {
            return app(\App\Actions\Documents\CalculateDocumentTotals::class)
                ->handle($input, (string) $this->discountRate);
        } catch (\DomainException|\ValueError $exception) {
            return null;
        }
    }


    /** @return \Illuminate\Database\Eloquent\Collection<int, Product> */

    /** @return \Illuminate\Database\Eloquent\Collection<int, Contact> */
    public function contactOptions(): \Illuminate\Database\Eloquent\Collection
    {
        $search = mb_substr(trim($this->contactSearch), 0, 80);
        $query = Contact::query()->where('is_active', true);

        if ($search !== '') {
            $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(fn ($builder) => $builder
                ->where('code', 'ilike', $needle)
                ->orWhere('title', 'ilike', $needle));
        }

        $options = $query->orderBy('title')->limit(35)->get();

        if ($this->contactId && ! $options->contains('id', $this->contactId)) {
            $selected = Contact::query()->find($this->contactId);
            if ($selected !== null) {
                $options->prepend($selected);
            }
        }

        return $options;
    }

    public function productOptionsForLine(string $rowKey, int $productId = 0): \Illuminate\Database\Eloquent\Collection
    {
        $search = mb_substr(trim((string) ($this->productSearches[$rowKey] ?? '')), 0, 80);
        $query = Product::query()->where('is_active', true);

        if ($search !== '') {
            $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(fn ($builder) => $builder
                ->where('code', 'ilike', $needle)
                ->orWhere('name', 'ilike', $needle)
                ->orWhere('barcode', 'ilike', $needle));
        }

        if ($search === '') {
            $this->defaultProductOptions ??= $query->orderBy('name')->limit(35)->get();
            $options = clone $this->defaultProductOptions;
        } else {
            $options = $query->orderBy('name')->limit(35)->get();
        }

        if ($productId > 0 && ! $options->contains('id', $productId)) {
            $selected = Product::query()->find($productId);
            if ($selected !== null) {
                $options->prepend($selected);
            }
        }

        return $options;
    }

    public function render(): View
    {
        return view('livewire.purchases.document-editor', [
            'title' => $this->pageTitle(),
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(),
        ])->layout('layouts.app', ['pageTitle' => $this->pageTitle()]);
    }

    protected function loadDocument(Document $document): void
    {
        $document->loadMissing(['lines.unit', 'contact', 'incomingRelations.sourceDocument']);
        $this->document = $document;
        $this->contactId = $document->contact_id;
        $this->documentDate = $document->document_date->toDateString();
        $this->dueDate = $document->due_date?->toDateString();
        $this->currency = (string) $document->currency;
        $this->exchangeRate = (string) $document->exchange_rate;
        $this->discountRate = (string) $document->discount_rate;
        $this->notes = (string) ($document->notes ?? '');
        $this->version = (int) $document->version;
        $this->lines = $document->lines->map(fn ($line) => [
            'row_key' => 'saved-'.$line->id,
            'line_total' => (string) $line->line_total,
            'line_kind' => $line->line_kind,
            'product_id' => $line->product_id,
            'description' => $line->description,
            'unit_id' => $line->unit_id,
            'quantity' => (string) $line->quantity,
            'conversion_factor' => $line->conversion_factor,
            'location_id' => $line->location_id,
            'unit_price' => (string) $line->unit_price,
            'line_discount_rate' => (string) $line->line_discount_rate,
            'line_discount_amount' => (string) $line->line_discount_amount,
            'vat_rate' => (string) $line->vat_rate,
            'configuration' => $line->configuration,
            'source_line_id' => $line->source_line_id,
        ])->all();
    }

    /** @return array<string,mixed> */
    private function emptyLine(): array
    {
        return [
            'row_key' => 'new-'.(string) \Illuminate\Support\Str::uuid(),
            'line_kind' => 'stock',
            'product_id' => null,
            'description' => '',
            'unit_id' => null,
            'quantity' => '1.000',
            'conversion_factor' => null,
            'location_id' => null,
            'unit_price' => '0',
            'line_discount_rate' => '0',
            'line_discount_amount' => '0',
            'vat_rate' => '20',
            'configuration' => null,
            'source_line_id' => null,
        ];
    }
}
