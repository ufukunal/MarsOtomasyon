<?php

namespace App\Livewire\Sales;

use App\Actions\Documents\ReverseDocument;
use App\Actions\Sales\PostSalesInvoice;
use App\Actions\Sales\CalculateSalesRiskProjection;
use App\DataObjects\Sales\SalesRiskProjection;
use App\Enums\DocumentType;

class SalesInvoiceEditor extends BaseSalesDocumentEditor
{
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
        return ['post', 'reverse'];
    }

    public function mount(?int $id = null): void
    {
        parent::mount($id);
        $this->reversalDate = now()->toDateString();
    }

    public function post(PostSalesInvoice $action): void
    {
        $this->requireSavedDraft();
        abort_unless($this->document !== null, 422);
        $this->loadDocument($action->handle($this->document, $this->mutationKey('post'), $this->riskAccepted)->load('lines'));
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
    public function contactRiskOverview(): ?array
    {
        if (! $this->contactId || ! auth()->user()?->can('contacts.view')) {
            return null;
        }

        $currency = (string) ($this->document?->currency ?? 'TRY');
        if ($currency !== 'TRY') {
            return null;
        }

        $contact = \App\Models\Period\Contact::query()->find($this->contactId);
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
            : (string) ($preview?->grandTotal ?? $this->document?->grand_total ?? '0');
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
