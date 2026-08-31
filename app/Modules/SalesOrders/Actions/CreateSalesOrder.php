<?php

namespace App\Modules\SalesOrders\Actions;

use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditSource;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Core\Enums\DocumentType;
use App\Modules\Core\Numbering\DocumentNumberIssuer;
use App\Modules\SalesOrders\Enums\SalesOrderStatus;
use App\Modules\SalesOrders\Models\SalesOrder;
use App\Modules\SalesOrders\Reservations\SalesOrderReservationSynchronizer;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateSalesOrder
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private SalesOrderDraftResolver $resolver,
        private DocumentNumberIssuer $numbers,
        private AuditRecorder $audit,
        private SalesOrderAuditSnapshot $auditSnapshot,
        private SalesOrderReservationSynchronizer $reservations,
    ) {}

    /** @param array<string, mixed> $auditMetadata */
    public function handle(
        SalesOrderDraftData $data,
        string $seriesCode = 'default',
        AuditSource $auditSource = AuditSource::Web,
        array $auditMetadata = [],
    ): SalesOrder {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        $seriesCode = mb_strtolower(trim($seriesCode));
        if (preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/D', $seriesCode) !== 1 || strlen($seriesCode) > 64) {
            throw ValidationException::withMessages(['series_code' => 'Sipariş numara serisi canonical ve en fazla 64 karakter olmalıdır.']);
        }
        if ($auditSource === AuditSource::Web && ! Auth::check() && app()->runningInConsole()) {
            $auditSource = AuditSource::Job;
        }

        $draft = $this->resolver->resolve($companyId, $data);

        try {
            return DB::transaction(function () use ($companyId, $seriesCode, $draft, $auditSource, $auditMetadata): SalesOrder {
                $number = $this->numbers->issue($companyId, DocumentType::SalesOrder, $seriesCode);
                $calculation = $draft->calculation;

                $order = SalesOrder::query()->create([
                    'company_id' => $companyId,
                    'account_id' => $draft->accountId,
                    'number' => $number->number,
                    'series_code' => $number->seriesCode,
                    'sequence_value' => $number->sequenceValue,
                    'status' => SalesOrderStatus::Draft,
                    'source_quote_id' => null,
                    'source_quote_revision_id' => null,
                    'order_date' => $draft->orderDate,
                    'currency_code' => $draft->currencyCode,
                    'document_discount_rate' => $draft->documentDiscountRate,
                    'base_net_total' => $calculation->baseNet,
                    'line_discount_total' => $calculation->lineDiscountNet,
                    'document_discount_total' => $calculation->documentDiscountNet,
                    'net_total' => $calculation->net,
                    'tax_total' => $calculation->tax,
                    'gross_total' => $calculation->gross,
                    'note' => $draft->note,
                ]);

                $this->persistLines($order, $draft);
                $this->reservations->sync($order, $draft);
                $this->audit->record(
                    AuditAction::SalesOrderCreated,
                    AuditTargetType::SalesOrder,
                    $order->getKey(),
                    after: $this->auditSnapshot->capture($order),
                    metadata: $auditMetadata,
                    source: $auditSource,
                );

                return $order->load('lines');
            });
        } catch (DomainException $exception) {
            throw ValidationException::withMessages(['series_code' => $exception->getMessage()]);
        }
    }

    private function persistLines(SalesOrder $order, ResolvedSalesOrderDraft $draft): void
    {
        foreach ($draft->lines as $line) {
            $result = $line->calculation;
            $order->lines()->create([
                'company_id' => $order->company_id,
                'source_quote_revision_line_id' => null,
                'logical_line_key' => $line->logicalLineKey,
                'position' => $line->position,
                'product_id' => $line->productId,
                'warehouse_id' => $line->warehouseId,
                'location_id' => $line->locationId,
                'product_code' => $line->productCode,
                'product_name' => $line->productName,
                'description' => $line->description,
                'quantity' => $result->quantity,
                'price_basis' => $result->priceBasis,
                'unit_price' => $result->unitPrice,
                'line_discount_rate' => $result->lineDiscountRate,
                'tax_id' => $line->taxId,
                'tax_code' => $line->taxCode,
                'tax_rate' => $result->taxRate,
                'tax_is_zeroed' => $line->taxIsZeroed,
                'tax_zero_reason_id' => $line->taxZeroReasonId,
                'tax_zero_reason_code' => $result->taxZeroReasonCode,
                'base_net' => $result->baseNet,
                'line_discount_net' => $result->lineDiscountNet,
                'document_discount_net' => $result->documentDiscountNet,
                'net_total' => $result->net,
                'tax_total' => $result->tax,
                'gross_total' => $result->gross,
            ]);
        }
    }
}
