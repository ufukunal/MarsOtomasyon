<?php

namespace App\Livewire\Sales;

use App\Actions\Documents\ReverseDocument;
use App\Actions\Documents\SaveSalesDocumentDraft;
use App\Actions\Sales\CalculateSalesRiskProjection;
use App\DataObjects\Sales\SalesRiskProjection;
use App\Enums\DocumentType;
use App\Models\Period\Contact;

class SalesInvoiceEditor extends BaseSalesDocumentEditor
{
    /** @return list<string> */
    protected function allowedRecordDeletionActions(): array
    {
        return ['deleteInvoice'];
    }

    public string $reversalDate = '';

    public string $reversalReason = '';

    public bool $riskAccepted = false;

    protected function documentType(): DocumentType
    {
        return DocumentType::SalesInvoice;
    }

    protected function permissionPrefix(): string
    {
        return 'sales_invoices';
    }

    protected function pageTitle(): string
    {
        return $this->document ? 'Satış Faturası' : 'Yeni Satış Faturası';
    }

    protected function extraMutationNames(): array
    {
        return ['post', 'reverse', 'invoiceEdit', 'invoiceDelete'];
    }

    public function mount(?int $id = null): void
    {
        parent::mount($id);

        // Default only new/unspecified draft invoices; retain saved choices.
        if ($this->document === null || $this->document->status === 'draft') {
            $this->uiMeta['payment_method'] ??= 'Vadeli';
        }

        // New invoice from a Cari card must start with that customer's discount.
        // Existing drafts retain their saved document discount, including manual edits.
        if ($this->document === null && $this->contactId !== null) {
            $this->applySelectedContactDiscount();
        }

        $this->reversalDate = now()->toDateString();
    }

    /** Apply the chosen contact's document discount without changing line prices. */
    public function updatedContactId(): void
    {
        $this->applySelectedContactDiscount();
    }

    private function applySelectedContactDiscount(): void
    {
        $contactRate = $this->contactId
            ? Contact::query()->where('is_active', true)->whereKey($this->contactId)->value('discount_rate')
            : null;

        // With no selected contact the invoice has no inherited discount.
        // After selection users can still override the visible invoice percentage.
        $rate = (string) ($contactRate ?? '0');
        $this->discountRate = str_contains($rate, '.')
            ? (rtrim(rtrim($rate, '0'), '.') ?: '0')
            : $rate;
    }

    protected function resetPrototypeTypeFields(): void
    {
        parent::resetPrototypeTypeFields();
        $this->uiMeta['payment_method'] = 'Vadeli';
    }

    /** The single visible Save button also posts the invoice to the ledger. */
    public function save(SaveSalesDocumentDraft $action): void
    {
        // Posting requires update permission even when saving a brand-new invoice.
        abort_unless(auth()->user()?->can($this->permissionPrefix().'.update'), 403);
        parent::save($action);
        $this->post(app(\App\Actions\Documents\ManageInvoiceRecord::class));
    }

    public function post(\App\Actions\Documents\ManageInvoiceRecord $action): void
    {
        $this->requireSavedDraft();
        abort_unless($this->document !== null, 422);
        $this->loadDocument($action->save($this->document, $this->mutationKey('post'), riskAccepted: $this->riskAccepted)->load('lines'));
        $this->completeMutation('post');
    }

    public function invoiceRiskProjection(): ?SalesRiskProjection
    {
        if (! $this->document || $this->document->status !== 'draft'
            || $this->document->contact_id === null || $this->document->currency !== 'TRY') {
            return null;
        }

        return app(CalculateSalesRiskProjection::class)->handle($this->document);
    }

    /**
     * TRY-only informational view. This is not a credit approval and does not
     * include security or foreign-currency exposure.
     *
     * @return array<string,string|bool|null>|null
     */

    /**
     * Read-only invoice contact details. Never expose tax or contact fields
     * without permission to view the selected contact.
     *
     * @return array<string, string|array<int,string>>|null
     */
    public function invoiceContactDetails(): ?array
    {
        if (! $this->contactId || ! auth()->user()?->can('contacts.view')) {
            return null;
        }

        $contact = Contact::query()
            ->with('categories')
            ->find($this->contactId);

        if ($contact === null) {
            return null;
        }

        return [
            'title' => (string) $contact->title,
            'tax_office' => (string) ($contact->tax_office ?? ''),
            'tax_number' => (string) ($contact->tax_number ?? ''),
            'address' => (string) ($contact->address ?? ''),
            'city' => (string) ($contact->city ?? ''),
            'district' => (string) ($contact->district ?? ''),
            'phone' => (string) ($contact->phone ?? ''),
            'email' => (string) ($contact->email ?? ''),
            'categories' => $contact->categories->pluck('name')->values()->all(),
        ];
    }

    public function contactRiskOverview(): ?array
    {
        if (! $this->contactId || ! auth()->user()?->can('contacts.view')) {
            return null;
        }

        $currency = (string) data_get($this->document, 'currency', 'TRY');
        if ($currency !== 'TRY') {
            return null;
        }

        $contact = Contact::query()->find($this->contactId);
        if (! $contact) {
            return null;
        }

        $balance = $contact->balance();
        $limit = bcadd((string) ($contact->risk_limit ?? '0'), '0', 4);
        $configured = bccomp($limit, '0', 4) > 0;
        $preview = $this->draftTotalsPreview();
        // Posted invoices already affect ledger balance; adding them again would double-count risk.
        $amount = $this->document && $this->document->status !== 'draft'
            ? '0'
            : (string) data_get($preview, 'grandTotal', data_get($this->document, 'grand_total', '0'));
        $exposure = bcadd($balance, $amount, 4);

        return [
            'balance' => $balance,
            'limit' => $limit,
            'configured' => $configured,
            'available' => $configured ? bcsub($limit, $balance, 4) : null,
            'projected' => $exposure,
            'exceeded' => $configured && bccomp($exposure, $limit, 4) > 0,
            'is_preview' => $preview !== null,
            'is_posted' => $this->document !== null && $this->document->status !== 'draft',
        ];
    }

    /**
     * One-click invoice edit: keep the posted ledger immutable, open a safe
     * editable draft after its internal reversal.
     */
    public function editInvoice(\App\Actions\Documents\ManageInvoiceRecord $action): mixed
    {
        abort_unless($this->document !== null, 422);
        $draft = $action->edit($this->document, $this->mutationKey('invoiceEdit'));
        $this->completeMutation('invoiceEdit');

        return $this->redirectRoute('sales.invoices.edit', ['id' => $draft->id], navigate: false);
    }

    public function deleteInvoice(\App\Actions\Documents\ManageInvoiceRecord $action): mixed
    {
        $this->requireVerifiedRecordDeletion('deleteInvoice');
        abort_unless($this->document !== null, 422);
        $action->delete($this->document, $this->mutationKey('invoiceDelete'));
        $this->completeMutation('invoiceDelete');

        return $this->redirectRoute('sales.invoices.index', navigate: false);
    }

    public function reverse(ReverseDocument $action): mixed
    {
        abort_unless($this->document !== null, 422);
        $reversal = $action->handle(
            $this->document,
            $this->reversalDate,
            $this->reversalReason,
            $this->mutationKey('reverse'),
        );
        $this->completeMutation('reverse');

        return $this->redirectRoute('sales.invoices.edit', ['id' => $reversal->id], navigate: false);
    }
}
