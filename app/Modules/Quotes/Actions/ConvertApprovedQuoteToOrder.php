<?php

namespace App\Modules\Quotes\Actions;

use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Core\Enums\DocumentType;
use App\Modules\Core\Numbering\DocumentNumberIssuer;
use App\Modules\Quotes\Enums\QuoteStatus;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Quotes\Models\QuoteRevision;
use App\Modules\SalesOrders\Enums\SalesOrderStatus;
use App\Modules\SalesOrders\Models\SalesOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class ConvertApprovedQuoteToOrder
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private DocumentNumberIssuer $numberIssuer,
        private AuditRecorder $audit,
    ) {}

    public function handle(int $quoteId, string $seriesCode = 'default'): SalesOrder
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();

        return DB::transaction(function () use ($companyId, $quoteId, $seriesCode): SalesOrder {
            $quote = Quote::query()
                ->where('company_id', $companyId)
                ->whereKey($quoteId)
                ->lockForUpdate()
                ->first();

            if ($quote === null) {
                throw ValidationException::withMessages(['quote' => 'Teklif aktif şirkette bulunamadı.']);
            }

            $existing = SalesOrder::query()
                ->where('company_id', $companyId)
                ->where('source_quote_id', $quoteId)
                ->first();
            if ($existing !== null) {
                if ($quote->statusEnum() !== QuoteStatus::Converted) {
                    throw new LogicException('Existing quote conversion order requires converted quote status.');
                }

                return $existing->load(['lines', 'sourceRevision']);
            }

            if (! $quote->isApproved() || $quote->selected_revision_id === null) {
                throw ValidationException::withMessages(['quote' => 'Yalnız seçili revizyonu onaylanmış teklif siparişe dönüştürülebilir.']);
            }

            $revisionId = (int) $quote->selected_revision_id;
            $revision = QuoteRevision::query()
                ->with('lines')
                ->where('company_id', $companyId)
                ->where('quote_id', $quoteId)
                ->whereKey($revisionId)
                ->first();
            if ($revision === null || $revision->lines->isEmpty()) {
                throw new LogicException('Approved quote conversion requires the selected persisted revision and lines.');
            }

            $issued = $this->numberIssuer->issue($companyId, DocumentType::SalesOrder, $seriesCode);
            $order = SalesOrder::query()->create([
                'company_id' => $companyId,
                'account_id' => (int) $revision->account_id,
                'number' => $issued->number,
                'series_code' => $issued->seriesCode,
                'sequence_value' => $issued->sequenceValue,
                'status' => SalesOrderStatus::Draft->value,
                'source_quote_id' => $quoteId,
                'source_quote_revision_id' => $revisionId,
                'order_date' => now()->toDateString(),
                'currency_code' => (string) $revision->currency_code,
                'document_discount_rate' => $this->rawString($revision, 'document_discount_rate'),
                'base_net_total' => $this->rawString($revision, 'base_net_total'),
                'line_discount_total' => $this->rawString($revision, 'line_discount_total'),
                'document_discount_total' => $this->rawString($revision, 'document_discount_total'),
                'net_total' => $this->rawString($revision, 'net_total'),
                'tax_total' => $this->rawString($revision, 'tax_total'),
                'gross_total' => $this->rawString($revision, 'gross_total'),
                'note' => $revision->note === null ? null : (string) $revision->note,
            ]);

            foreach ($revision->lines as $line) {
                $order->lines()->create([
                    'company_id' => $companyId,
                    'source_quote_revision_line_id' => (int) $line->getKey(),
                    'position' => (int) $line->position,
                    'product_id' => (int) $line->product_id,
                    'product_code' => (string) $line->product_code,
                    'product_name' => (string) $line->product_name,
                    'description' => (string) $line->description,
                    'quantity' => $this->rawString($line, 'quantity'),
                    'price_basis' => $this->rawString($line, 'price_basis'),
                    'unit_price' => $this->rawString($line, 'unit_price'),
                    'line_discount_rate' => $this->rawString($line, 'line_discount_rate'),
                    'tax_id' => (int) $line->tax_id,
                    'tax_code' => (string) $line->tax_code,
                    'tax_rate' => $this->rawString($line, 'tax_rate'),
                    'tax_zero_reason_id' => $line->tax_zero_reason_id === null ? null : (int) $line->tax_zero_reason_id,
                    'tax_zero_reason_code' => $line->tax_zero_reason_code === null ? null : (string) $line->tax_zero_reason_code,
                    'base_net' => $this->rawString($line, 'base_net'),
                    'line_discount_net' => $this->rawString($line, 'line_discount_net'),
                    'document_discount_net' => $this->rawString($line, 'document_discount_net'),
                    'net_total' => $this->rawString($line, 'net_total'),
                    'tax_total' => $this->rawString($line, 'tax_total'),
                    'gross_total' => $this->rawString($line, 'gross_total'),
                ]);
            }

            $this->audit->record(
                AuditAction::SalesOrderCreated,
                AuditTargetType::SalesOrder,
                $order->getKey(),
                after: [
                    'number' => $issued->number,
                    'source_quote_id' => $quoteId,
                    'source_quote_revision_id' => $revisionId,
                ],
            );

            $quote->fill([
                'status' => QuoteStatus::Converted->value,
                'converted_at' => now(),
            ])->save();

            $this->audit->record(
                AuditAction::QuoteConverted,
                AuditTargetType::Quote,
                $quote->getKey(),
                before: [
                    'status' => QuoteStatus::Approved->value,
                    'selected_revision_id' => $revisionId,
                ],
                after: [
                    'status' => QuoteStatus::Converted->value,
                    'selected_revision_id' => $revisionId,
                    'sales_order_id' => (int) $order->getKey(),
                    'sales_order_number' => $issued->number,
                ],
            );

            return $order->load(['lines', 'sourceRevision']);
        });
    }

    private function rawString(Model $model, string $attribute): string
    {
        $raw = $model->getRawOriginal($attribute);
        if (! is_string($raw) && ! is_int($raw)) {
            throw new LogicException('Persisted '.$attribute.' must be scalar.');
        }

        return (string) $raw;
    }
}
