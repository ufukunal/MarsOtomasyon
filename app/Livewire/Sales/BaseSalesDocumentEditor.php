<?php

namespace App\Livewire\Sales;

use App\Actions\Documents\SaveSalesDocumentDraft;
use App\Enums\DocumentType;
use App\Enums\LocationKind;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period\Contact;
use App\Models\Period\Document;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Models\Period\Unit;
use Illuminate\Contracts\View\View;
use Livewire\Component;

abstract class BaseSalesDocumentEditor extends Component
{
    use WithIdempotentMutations;

    public ?Document $document = null;

    public ?int $contactId = null;

    public string $documentDate = '';

    public ?string $dueDate = null;

    public ?string $validUntil = null;

    public string $discountRate = '0';

    public string $bulkVatRate = '20';

    public string $notes = '';

    public int $version = 1;

    public bool $documentDirty = false;

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
            // A new document started from Cari Detay keeps its originating cari,
            // without affecting edits or trusting an unvalidated URL id.
            $selectedCari = request()->query('cari');
            if (is_scalar($selectedCari) && preg_match('/^[1-9][0-9]*$/D', (string) $selectedCari)) {
                $this->contactId = Contact::query()
                    ->where('is_active', true)
                    ->whereKey((int) $selectedCari)
                    ->value('id');
            }
            $this->lines = [$this->emptyLine()];
        }
    }

    public function updated(string $property): void
    {
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
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
        $this->documentDirty = true;
    }

    public function applyVatToAll(): void
    {
        $rate = bcadd($this->bulkVatRate, '0', 4);

        abort_if(
            bccomp($rate, '0', 4) < 0 || bccomp($rate, '100', 4) > 0,
            422,
            'KDV oranı 0 ile 100 arasında olmalıdır.',
        );

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

    public function save(SaveSalesDocumentDraft $action): void
    {
        abort_unless(auth()->user()?->can(
            $this->permissionPrefix().($this->document ? '.update' : '.create')
        ), 403);

        $saved = $this->runPeriodMutation('save', fn () => $action->handle(
            $this->documentType(),
            [
                'contact_id' => $this->contactId,
                'document_date' => $this->documentDate,
                'due_date' => $this->dueDate,
                'valid_until' => $this->validUntil,
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

    public function render(): View
    {
        return view('livewire.sales.document-editor', [
            'title' => $this->pageTitle(),
            'contacts' => Contact::query()->where('is_active', true)->orderBy('title')->limit(500)->get(),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->limit(500)->get(),
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(),
            'locations' => Location::query()
                ->where('is_active', true)
                ->where('kind', '!=', LocationKind::Subcontractor->value)
                ->orderBy('name')
                ->get(),
        ])->layout('layouts.app', ['pageTitle' => $this->pageTitle()]);
    }

    protected function loadDocument(Document $document): void
    {
        $document->loadMissing(['lines.unit', 'contact']);
        $this->document = $document;
        $this->contactId = $document->contact_id;
        $this->documentDate = $document->document_date->toDateString();
        $this->dueDate = $document->due_date?->toDateString();
        $this->validUntil = $document->valid_until?->toDateString();
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
            'reserve_stock' => (bool) $line->reserve_stock,
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
            'unit_price' => '',
            'line_discount_rate' => '0',
            'line_discount_amount' => '0',
            'vat_rate' => '20',
            'reserve_stock' => false,
            'configuration' => null,
            'source_line_id' => null,
        ];
    }
}
