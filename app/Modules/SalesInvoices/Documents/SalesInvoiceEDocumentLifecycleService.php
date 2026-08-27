<?php

namespace App\Modules\SalesInvoices\Documents;

use App\Foundation\Clock\Clock;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\SalesInvoices\Enums\SalesInvoiceEDocumentEventType;
use App\Modules\SalesInvoices\Enums\SalesInvoiceEDocumentType;
use App\Modules\SalesInvoices\Enums\SalesInvoiceStatus;
use App\Modules\SalesInvoices\Models\SalesInvoice;
use App\Modules\SalesInvoices\Models\SalesInvoiceEDocumentEvent;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class SalesInvoiceEDocumentLifecycleService
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private Clock $clock,
    ) {}

    public function append(
        int $invoiceId,
        SalesInvoiceEDocumentType $documentType,
        SalesInvoiceEDocumentEventType $eventType,
        ?string $providerKey = null,
        ?string $externalDocumentId = null,
        ?string $payloadSha256 = null,
    ): SalesInvoiceEDocumentEvent {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        $providerKey = $providerKey === null ? null : strtolower(trim($providerKey));
        $externalDocumentId = $externalDocumentId === null || trim($externalDocumentId) === '' ? null : trim($externalDocumentId);
        $payloadSha256 = $payloadSha256 === null ? null : strtolower(trim($payloadSha256));

        return DB::transaction(function () use ($companyId, $invoiceId, $documentType, $eventType, $providerKey, $externalDocumentId, $payloadSha256): SalesInvoiceEDocumentEvent {
            $invoice = SalesInvoice::query()
                ->where('company_id', $companyId)
                ->whereKey($invoiceId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($invoice->statusEnum(), [SalesInvoiceStatus::Finalized, SalesInvoiceStatus::Cancelled], true)) {
                throw new LogicException('E-document lifecycle requires a finalized sales invoice.');
            }

            $previous = SalesInvoiceEDocumentEvent::query()
                ->where('company_id', $companyId)
                ->where('sales_invoice_id', $invoiceId)
                ->where('document_type', $documentType->value)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $this->assertTransition($invoice, $previous, $eventType, $providerKey, $payloadSha256);

            return SalesInvoiceEDocumentEvent::query()->create([
                'company_id' => $companyId,
                'sales_invoice_id' => $invoiceId,
                'document_type' => $documentType->value,
                'event_type' => $eventType->value,
                'provider_key' => $providerKey,
                'external_document_id' => $externalDocumentId,
                'payload_sha256' => $payloadSha256,
                'occurred_at' => $this->clock->now(),
            ]);
        });
    }

    public function current(int $invoiceId, SalesInvoiceEDocumentType $documentType): ?SalesInvoiceEDocumentEvent
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();

        return SalesInvoiceEDocumentEvent::query()
            ->where('company_id', $companyId)
            ->where('sales_invoice_id', $invoiceId)
            ->where('document_type', $documentType->value)
            ->orderByDesc('id')
            ->first();
    }

    private function assertTransition(
        SalesInvoice $invoice,
        ?SalesInvoiceEDocumentEvent $previous,
        SalesInvoiceEDocumentEventType $eventType,
        ?string $providerKey,
        ?string $payloadSha256,
    ): void {
        $previousType = $previous?->eventTypeEnum();

        if ($eventType === SalesInvoiceEDocumentEventType::Prepared) {
            if ($previous !== null || $invoice->statusEnum() === SalesInvoiceStatus::Cancelled || $providerKey !== null || $payloadSha256 !== null) {
                throw new LogicException('Prepared must start an empty provider-neutral e-document stream.');
            }

            return;
        }

        if (! $previous instanceof SalesInvoiceEDocumentEvent) {
            throw new LogicException('E-document stream must be prepared before provider lifecycle events.');
        }

        if ($eventType === SalesInvoiceEDocumentEventType::Submitted) {
            if ($invoice->statusEnum() === SalesInvoiceStatus::Cancelled
                || ! in_array($previousType, [SalesInvoiceEDocumentEventType::Prepared, SalesInvoiceEDocumentEventType::Rejected], true)
                || $providerKey === null
                || $payloadSha256 === null
                || preg_match('/^[0-9a-f]{64}$/', $payloadSha256) !== 1) {
                throw new LogicException('Submitted e-document event violates lifecycle contract.');
            }

            return;
        }

        if (in_array($eventType, [SalesInvoiceEDocumentEventType::Accepted, SalesInvoiceEDocumentEventType::Rejected], true)) {
            if ($invoice->statusEnum() === SalesInvoiceStatus::Cancelled
                || $previousType !== SalesInvoiceEDocumentEventType::Submitted
                || $providerKey === null
                || $providerKey !== $previous->provider_key) {
                throw new LogicException('Provider result e-document event violates lifecycle contract.');
            }

            return;
        }

        if ($eventType === SalesInvoiceEDocumentEventType::Cancelled) {
            if ($invoice->statusEnum() !== SalesInvoiceStatus::Cancelled
                || $previousType === SalesInvoiceEDocumentEventType::Cancelled
                || ($previous->provider_key !== null && $providerKey !== $previous->provider_key)) {
                throw new LogicException('Cancelled e-document event violates lifecycle contract.');
            }
        }
    }
}
