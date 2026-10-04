<?php

namespace App\Actions\Documents;

use App\Actions\Numbering\GenerateDocumentNumber;
use App\Actions\Periods\EnsurePeriodOpen;
use App\Actions\Purchases\PurchaseLineAvailability;
use App\Actions\Stock\ConsumeReservation;
use App\Actions\Stock\RecordStockMovement;
use App\DataObjects\Documents\DocumentPostingContext;
use App\DataObjects\StockMovementData;
use App\Enums\DocumentType;
use App\Models\Period\BankAccount;
use App\Models\Period\BankMovement;
use App\Models\Period\CashAccount;
use App\Models\Period\CashMovement;
use App\Models\Period\ContactTransaction;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\StockReservation;
use App\Support\Audit\AuditContext;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;

final class PostDocument
{
    public function __construct(
        private readonly CalculateDocumentTotals $calculator,
        private readonly ResolveDocumentPostingProfile $profiles,
        private readonly EnsurePeriodOpen $ensurePeriodOpen,
        private readonly GenerateDocumentNumber $numbers,
        private readonly RecordStockMovement $recordStockMovement,
        private readonly ConsumeReservation $consumeReservation,
        private readonly VerifyPostedDocument $verify,
        private readonly ResolveSourceLineage $lineage,
        private readonly SourceLineAvailability $availability,
        private readonly PurchaseLineAvailability $purchaseAvailability,
    ) {}

    public function handle(
        Document $document,
        string $idempotencyKey,
        ?DocumentPostingContext $context = null,
    ): Document {
        return IdempotencyKey::run(
            $idempotencyKey,
            'document.post:'.$document->id,
            function () use ($document, $idempotencyKey, $context): Document {
                $locked = Document::query()->with('lines')->lockForUpdate()->findOrFail($document->id);

                if ($locked->status === 'posted') {
                    throw new DomainException('Belge zaten kesinleştirilmiş.');
                }

                $this->ensurePeriodOpen->handle(CarbonImmutable::parse($locked->document_date));
                $profile = $this->profiles->handle($locked, $context);
                $this->assertTotals($locked, $profile->calculationMode);
                $this->assertSourceAvailability($locked);

                if ($locked->number === null) {
                    $locked->number = $this->numbers->handle(
                        $locked->document_type->value,
                        CarbonImmutable::parse($locked->document_date)->year,
                    );
                }

                $actor = auth()->user();

                foreach ($locked->lines as $line) {
                    if ($line->line_kind !== 'stock') {
                        continue;
                    }

                    $lineage = $this->lineage->handle($line);
                    $stockOut = $profile->stockOut
                        && ! ($locked->document_type === DocumentType::SalesInvoice && $lineage['has_dispatch']);

                    if ($stockOut || $profile->stockIn) {
                        if ($line->location_id === null || $line->base_quantity === null) {
                            throw new DomainException('Stok etkili belge satırında lokasyon ve temel miktar zorunludur.');
                        }

                        $this->recordStockMovement->handle(new StockMovementData(
                            productId: (int) $line->product_id,
                            locationId: (int) $line->location_id,
                            movementDate: $locked->document_date->toDateString(),
                            direction: $profile->stockIn ? 'in' : 'out',
                            reason: $locked->document_type->value,
                            quantity: (string) $line->base_quantity,
                            updatesAverage: false,
                            documentType: $locked->document_type->value,
                            documentId: (int) $locked->id,
                            documentNo: $locked->number,
                            note: $locked->notes,
                            actorUserId: $actor?->id,
                            actorUserName: $actor?->name,
                        ));
                    }

                    if ($profile->consumeReservations
                        && $lineage['origin_order_line_id'] !== null
                        && $line->location_id !== null
                        && $line->base_quantity !== null) {
                        $this->consumeForLine(
                            (int) $lineage['origin_order_line_id'],
                            (int) $line->product_id,
                            (int) $line->location_id,
                            (string) $line->base_quantity,
                            $idempotencyKey,
                            (int) $line->id,
                        );
                    }
                }

                if ($profile->contactDirection !== null) {
                    if ($locked->contact_id === null) {
                        throw new DomainException('Cari etkili belgede cari zorunludur.');
                    }

                    $contactAmount = $context?->contactAmount ?? (string) $locked->grand_total;
                    $contactCurrency = $context?->contactCurrency ?? (string) $locked->currency;

                    ContactTransaction::query()->create([
                        'contact_id' => $locked->contact_id,
                        'document_id' => $locked->id,
                        'transaction_type' => $locked->document_type->value,
                        'direction' => $profile->contactDirection,
                        'transaction_date' => $locked->document_date,
                        'due_date' => $locked->due_date,
                        'amount' => $contactAmount,
                        'currency' => $contactCurrency,
                        'description' => $context === null ? $locked->notes : ($context->reason ?? $locked->notes),
                        'created_by' => $actor?->id,
                        'created_by_name' => $actor?->name,
                    ]);
                }

                if ($profile->financialIn) {
                    $this->writeCollectionMovement($locked, $context, $actor?->id, $actor?->name);
                }

                if ($profile->financialOut) {
                    $this->writePaymentMovement($locked, $context, $actor?->id, $actor?->name);
                }

                $locked->status = 'posted';
                $locked->posted_by = $actor?->id;
                $locked->posted_by_name = $actor?->name;
                $locked->posted_at = now();
                $locked->version = (int) $locked->version + 1;
                $locked->save();
                $locked->load('lines');

                $this->verify->handle($locked, $profile, $context);

                AuditContext::period(
                    'Belge kesinleştirildi.',
                    ['document_id' => $locked->id, 'type' => $locked->document_type->value, 'number' => $locked->number],
                    $locked,
                    'document_posted',
                );

                return $locked->refresh();
            },
        );
    }

    private function assertSourceAvailability(Document $document): void
    {
        if (in_array($document->document_type, [DocumentType::GoodsReceipt, DocumentType::SupplierInvoice], true)) {
            $this->assertPurchaseSourceAvailability($document);

            return;
        }

        if (! in_array($document->document_type, [DocumentType::Dispatch, DocumentType::SalesInvoice], true)) {
            return;
        }

        $groups = [];

        foreach ($document->lines as $line) {
            $lineage = $this->lineage->handle($line);
            $sourceType = null;
            $sourceLineId = null;

            if ($document->document_type === DocumentType::Dispatch
                && $lineage['origin_order_line_id'] !== null) {
                $sourceType = 'order';
                $sourceLineId = $lineage['origin_order_line_id'];
            }

            if ($document->document_type === DocumentType::SalesInvoice) {
                if ($lineage['dispatch_line_id'] !== null) {
                    $sourceType = 'dispatch';
                    $sourceLineId = $lineage['dispatch_line_id'];
                } elseif ($lineage['origin_order_line_id'] !== null) {
                    $sourceType = 'order';
                    $sourceLineId = $lineage['origin_order_line_id'];
                }
            }

            if ($sourceType === null || $sourceLineId === null) {
                continue;
            }

            $key = $sourceType.':'.$sourceLineId;
            $groups[$key] ??= [
                'type' => $sourceType,
                'line_id' => $sourceLineId,
                'quantity' => '0.000',
            ];
            $groups[$key]['quantity'] = bcadd(
                $groups[$key]['quantity'],
                (string) $line->quantity,
                3,
            );
        }

        foreach ($groups as $group) {
            $source = DocumentLine::query()
                ->lockForUpdate()
                ->findOrFail((int) $group['line_id']);

            $remaining = $group['type'] === 'dispatch'
                ? $this->availability->dispatchRemaining($source)
                : $this->availability->orderRemaining($source);

            if (bccomp((string) $group['quantity'], $remaining, 3) > 0) {
                throw new DomainException('Belge kaynak satırın kalan miktarını aşıyor.');
            }
        }
    }

    private function assertPurchaseSourceAvailability(Document $document): void
    {
        $groups = [];

        foreach ($document->lines as $line) {
            if ($line->source_line_id === null) {
                throw new DomainException('Alış akışı belgesinde kaynak satır zorunludur.');
            }

            $source = DocumentLine::query()
                ->with('document')
                ->lockForUpdate()
                ->findOrFail((int) $line->source_line_id);

            if ($line->line_kind !== $source->line_kind
                || (int) ($line->product_id ?? 0) !== (int) ($source->product_id ?? 0)
                || (int) ($line->unit_id ?? 0) !== (int) ($source->unit_id ?? 0)
                || ($line->line_kind === 'stock'
                    && bccomp((string) $line->conversion_factor, (string) $source->conversion_factor, 6) !== 0)) {
                throw new DomainException('Alış belgesi kaynak satır ürün/birim snapshotıyla eşleşmiyor.');
            }

            $key = (string) $line->source_line_id;
            $groups[$key] = bcadd(
                $groups[$key] ?? '0.000',
                (string) $line->quantity,
                3,
            );
        }

        foreach ($groups as $sourceLineId => $quantity) {
            $source = DocumentLine::query()
                ->with('document')
                ->lockForUpdate()
                ->findOrFail((int) $sourceLineId);

            if ($document->document_type === DocumentType::GoodsReceipt) {
                if ($source->document->document_type !== DocumentType::PurchaseOrder
                    || ! in_array($source->document->status, ['approved', 'sent'], true)) {
                    throw new DomainException('Mal kabul yalnız onaylı/gönderilmiş satınalma siparişi satırından yapılabilir.');
                }

                if ((int) $source->document->contact_id !== (int) $document->contact_id
                    || $source->document->currency !== $document->currency) {
                    throw new DomainException('Mal kabul tedarikçi veya para birimi satınalma siparişiyle eşleşmiyor.');
                }

                $remaining = $this->purchaseAvailability->orderReceiptRemaining($source);
            } else {
                if ($source->document->document_type !== DocumentType::GoodsReceipt
                    || $source->document->status !== 'posted') {
                    throw new DomainException('Alış faturası yalnız kesinleşmiş mal kabul satırından üretilebilir.');
                }

                $remaining = $this->purchaseAvailability->receiptInvoiceRemaining($source);
            }

            if (bccomp($quantity, $remaining, 3) > 0) {
                throw new DomainException('Alış belgesi kaynak satırın kalan miktarını aşıyor.');
            }
        }
    }

    private function assertTotals(Document $document, string $calculationMode): void
    {
        if ($calculationMode === 'header_amount') {
            if ($document->lines->isNotEmpty()
                || bccomp((string) $document->grand_total, '0', 4) <= 0
                || bccomp((string) $document->subtotal, (string) $document->tax_base, 4) !== 0
                || bccomp((string) $document->tax_base, (string) $document->grand_total, 4) !== 0
                || bccomp((string) $document->discount_amount, '0', 4) !== 0
                || bccomp((string) $document->vat_amount, '0', 4) !== 0
                || bccomp((string) $document->rounding_difference, '0', 4) !== 0) {
                throw new DomainException('Header-amount belge toplam invariantı geçersiz.');
            }

            return;
        }

        $totals = $this->calculator->handle(
            $document->lines->map(fn ($line) => [
                'quantity' => (string) $line->quantity,
                'unit_price' => (string) $line->unit_price,
                'line_discount_rate' => (string) $line->line_discount_rate,
                'line_discount_amount' => (string) $line->line_discount_amount,
                'vat_rate' => (string) $line->vat_rate,
            ])->all(),
            (string) $document->discount_rate,
            (string) $document->discount_amount,
        );

        foreach ($document->lines->values() as $index => $line) {
            $expected = $totals->lines[$index];

            if (bccomp((string) $line->line_total, $expected->lineTotal, 4) !== 0) {
                throw new DomainException('Belge satır toplamı hesap motoruyla eşleşmiyor.');
            }

            if ($line->line_kind === 'stock') {
                $calculatedBase = bcadd(
                    bcmul((string) $line->quantity, (string) $line->conversion_factor, 6),
                    '0',
                    3,
                );

                if (bccomp((string) $line->base_quantity, $calculatedBase, 3) !== 0) {
                    throw new DomainException('Stock satır base_quantity dönüşüm snapshotı geçersiz.');
                }
            }
        }

        foreach ([
            'discount_amount' => $totals->discountAmount,
            'subtotal' => $totals->subtotal,
            'tax_base' => $totals->taxBase,
            'vat_amount' => $totals->vatAmount,
            'rounding_difference' => $totals->roundingDifference,
            'grand_total' => $totals->grandTotal,
        ] as $field => $expected) {
            if (bccomp((string) $document->getAttribute($field), $expected, 4) !== 0) {
                throw new DomainException("Belge {$field} toplamı hesap motoruyla eşleşmiyor.");
            }
        }
    }

    private function consumeForLine(
        int $orderLineId,
        int $productId,
        int $locationId,
        string $quantity,
        string $requestKey,
        int $postingLineId,
    ): void {
        $remaining = bcadd($quantity, '0', 3);

        $reservations = StockReservation::query()
            ->where('document_line_id', $orderLineId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->where('status', 'active')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        foreach ($reservations as $reservation) {
            if (bccomp($remaining, '0', 3) <= 0) {
                break;
            }

            $take = bccomp((string) $reservation->quantity, $remaining, 3) <= 0
                ? (string) $reservation->quantity
                : $remaining;

            $this->consumeReservation->handle(
                (int) $reservation->id,
                hash('sha256', $requestKey.':reservation:'.$postingLineId.':'.$reservation->id),
                $take,
            );

            $remaining = bcsub($remaining, $take, 3);
        }
    }

    private function writeCollectionMovement(
        Document $document,
        ?DocumentPostingContext $context,
        ?int $actorId,
        ?string $actorName,
    ): void {
        $this->writeFinancialMovement(
            $document,
            $context,
            $actorId,
            $actorName,
            'in',
            'Tahsilat',
        );
    }

    private function writePaymentMovement(
        Document $document,
        ?DocumentPostingContext $context,
        ?int $actorId,
        ?string $actorName,
    ): void {
        $this->writeFinancialMovement(
            $document,
            $context,
            $actorId,
            $actorName,
            'out',
            'Ödeme',
        );
    }

    private function writeFinancialMovement(
        Document $document,
        ?DocumentPostingContext $context,
        ?int $actorId,
        ?string $actorName,
        string $direction,
        string $label,
    ): void {
        if ($context === null
            || ! in_array($context->accountType, ['cash', 'bank'], true)
            || $context->accountId === null) {
            throw new DomainException("{$label} için kasa veya banka hesabı zorunludur.");
        }

        if ($context->accountType === 'cash') {
            $account = CashAccount::query()->where('is_active', true)->findOrFail($context->accountId);

            if ($account->currency !== $document->currency) {
                throw new DomainException("Kasa para birimi {$label} para birimiyle eşleşmiyor.");
            }

            CashMovement::query()->create([
                'cash_account_id' => $account->id,
                'document_id' => $document->id,
                'contact_id' => $document->contact_id,
                'movement_date' => $document->document_date,
                'direction' => $direction,
                'amount' => $document->grand_total,
                'description' => $document->notes,
                'created_by' => $actorId,
                'created_by_name' => $actorName,
            ]);

            return;
        }

        $account = BankAccount::query()->where('is_active', true)->findOrFail($context->accountId);

        if ($account->currency !== $document->currency) {
            throw new DomainException("Banka para birimi {$label} para birimiyle eşleşmiyor.");
        }

        BankMovement::query()->create([
            'bank_account_id' => $account->id,
            'document_id' => $document->id,
            'contact_id' => $document->contact_id,
            'movement_date' => $document->document_date,
            'direction' => $direction,
            'amount' => $document->grand_total,
            'description' => $document->notes,
            'created_by' => $actorId,
            'created_by_name' => $actorName,
        ]);
    }
}
