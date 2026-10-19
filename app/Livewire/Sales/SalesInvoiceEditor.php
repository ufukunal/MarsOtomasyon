<?php

namespace App\Livewire\Sales;

use App\Actions\Documents\ReverseDocument;
use App\Actions\Documents\SaveSalesDocumentDraft;
use App\Actions\Sales\CalculateSalesRiskProjection;
use App\DataObjects\Sales\SalesRiskProjection;
use App\Enums\DocumentType;
use App\Models\Period\Contact;
use App\Models\Period\ContactAddress;
use Illuminate\Validation\ValidationException;

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
            $this->uiMeta['delivery_mode'] ??= 'customer';
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
        $this->clearInvoiceDelivery();
        $this->uiMeta['delivery_mode'] = 'customer';
    }

    public function updatedUiMetaDeliveryMode(): void
    {
        $mode = (string) ($this->uiMeta['delivery_mode'] ?? 'customer');
        $this->clearInvoiceDelivery();
        $this->uiMeta['delivery_mode'] = $mode;
    }

    public function updatedUiMetaDeliveryAddressId(): void
    {
        $this->fillInvoiceDeliveryFromAddress();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, ContactAddress> */
    public function invoiceDeliveryAddresses(): \Illuminate\Database\Eloquent\Collection
    {
        if (! $this->contactId || ! auth()->user()?->can('contacts.view')) {
            return new \Illuminate\Database\Eloquent\Collection;
        }

        return ContactAddress::query()
            ->where('contact_id', $this->contactId)
            ->where('type', 'shipping')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();
    }

    private function clearInvoiceDelivery(): void
    {
        foreach (['delivery_address_id', 'delivery_company', 'delivery_person',
            'delivery_phone', 'delivery_city', 'delivery_district', 'delivery_address'] as $field) {
            unset($this->uiMeta[$field]);
        }
    }

    private function fillInvoiceDeliveryFromAddress(): void
    {
        $id = (int) ($this->uiMeta['delivery_address_id'] ?? 0);
        $contact = $this->contactId
            ? Contact::query()->where('is_active', true)->find($this->contactId)
            : null;
        $address = $contact && $id > 0
            ? ContactAddress::query()->where('contact_id', $contact->id)
                ->where('type', 'shipping')->find($id)
            : null;

        // Keep a selected address bound to the invoice's billed customer.
        foreach (['delivery_company', 'delivery_person', 'delivery_phone',
            'delivery_city', 'delivery_district', 'delivery_address'] as $field) {
            unset($this->uiMeta[$field]);
        }
        if (! $address) {
            return;
        }

        $this->uiMeta['delivery_company'] = (string) ($address->title ?: $contact->title);
        $this->uiMeta['delivery_city'] = (string) ($address->city ?? '');
        $this->uiMeta['delivery_district'] = (string) ($address->district ?? '');
        $this->uiMeta['delivery_address'] = (string) $address->address;
        // A shipping address has no contact-specific phone in the current schema.
        // Do not copy the billed customer's telephone to the third-party recipient.
    }

    private function prepareInvoiceDelivery(): void
    {
        $mode = (string) ($this->uiMeta['delivery_mode'] ?? 'customer');
        if (! in_array($mode, ['customer', 'saved', 'manual'], true)) {
            throw ValidationException::withMessages(['uiMeta.delivery_mode' => 'Geçersiz sevk şekli.']);
        }
        $this->uiMeta['delivery_mode'] = $mode;

        if ($mode === 'customer') {
            $this->clearInvoiceDelivery();
            $contact = $this->contactId
                ? Contact::query()->where('is_active', true)->with('addresses')->find($this->contactId)
                : null;
            if (! $contact) {
                return; // The base invoice validator reports the missing/invalid contact.
            }
            $address = $contact->addresses->where('type', 'shipping')
                ->sortByDesc('is_default')->first();
            $this->uiMeta['delivery_company'] = (string) $contact->title;
            $this->uiMeta['delivery_person'] = (string) ($contact->primary_contact_name ?? '');
            $this->uiMeta['delivery_phone'] = (string) ($contact->phone ?? '');
            $this->uiMeta['delivery_city'] = (string) ($address?->city ?? $contact->city ?? '');
            $this->uiMeta['delivery_district'] = (string) ($address?->district ?? $contact->district ?? '');
            $this->uiMeta['delivery_address'] = (string) ($address?->address ?? $contact->address ?? '');

            return;
        }

        if ($mode === 'saved') {
            $id = (int) ($this->uiMeta['delivery_address_id'] ?? 0);
            $belongsToContact = $id > 0 && $this->contactId
                && ContactAddress::query()->where('contact_id', $this->contactId)
                    ->where('type', 'shipping')->whereKey($id)->exists();
            if (! $belongsToContact) {
                throw ValidationException::withMessages([
                    'uiMeta.delivery_address_id' => 'Sevk adresini seçili müşterinin kartından seçin.',
                ]);
            }
        } else {
            unset($this->uiMeta['delivery_address_id']);
        }

        foreach ([
            'delivery_company' => 180, 'delivery_phone' => 40,
            'delivery_city' => 100, 'delivery_district' => 100,
            'delivery_address' => 4000,
        ] as $field => $maxLength) {
            $value = trim((string) ($this->uiMeta[$field] ?? ''));
            if ($value === '' || mb_strlen($value) > $maxLength) {
                throw ValidationException::withMessages([
                    'uiMeta.'.$field => 'Teslimat bilgisi zorunludur; en fazla '.$maxLength.' karakter olabilir.',
                ]);
            }
            $this->uiMeta[$field] = $value;
        }
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
        $this->clearInvoiceDelivery();
        $this->uiMeta['delivery_mode'] = 'customer';
    }

    /** The single visible Save button also posts the invoice to the ledger. */
    public function save(SaveSalesDocumentDraft $action): void
    {
        // Posting requires update permission even when saving a brand-new invoice.
        abort_unless(auth()->user()?->can($this->permissionPrefix().'.update'), 403);
        $this->prepareInvoiceDelivery();
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
     * @return array<string, string|bool|array<int,string>>|null
     */
    public function invoiceContactDetails(): ?array
    {
        if (! $this->contactId || ! auth()->user()?->can('contacts.view')) {
            return null;
        }

        $contact = Contact::query()
            ->with(['categories', 'addresses'])
            ->find($this->contactId);

        if ($contact === null) {
            return null;
        }

        // Address cards carry separate billing and shipping destinations.
        // Prefer defaults; do not substitute the invoice address for an undefined shipment.
        $billingAddress = $contact->addresses
            ->where('type', 'invoice')->sortByDesc('is_default')->first();
        $shippingAddress = $contact->addresses
            ->where('type', 'shipping')->sortByDesc('is_default')->first();

        return [
            'billing_address' => (string) ($billingAddress?->address ?? $contact->address ?? ''),
            'billing_city' => (string) ($billingAddress?->city ?? $contact->city ?? ''),
            'billing_district' => (string) ($billingAddress?->district ?? $contact->district ?? ''),
            'shipping_defined' => $shippingAddress !== null,
            'shipping_title' => (string) ($shippingAddress?->title ?? ''),
            'shipping_address' => (string) ($shippingAddress?->address ?? ''),
            'shipping_city' => (string) ($shippingAddress?->city ?? ''),
            'shipping_district' => (string) ($shippingAddress?->district ?? ''),
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
