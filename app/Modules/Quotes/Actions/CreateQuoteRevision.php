<?php

namespace App\Modules\Quotes\Actions;

use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Products\Models\Product;
use App\Modules\Quotes\Models\Quote;
use App\Modules\Quotes\Models\QuoteRevision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use JsonException;
use LogicException;

final readonly class CreateQuoteRevision
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private AuditRecorder $audit,
    ) {}

    /** @throws JsonException */
    public function handle(int $quoteId): QuoteRevision
    {
        $companyId = (int) $this->companyContext->requireCompany()->getKey();

        return DB::transaction(function () use ($companyId, $quoteId): QuoteRevision {
            $quote = Quote::query()
                ->where('company_id', $companyId)
                ->whereKey($quoteId)
                ->lockForUpdate()
                ->first();

            if ($quote === null) {
                throw ValidationException::withMessages(['quote' => 'Teklif aktif şirkette bulunamadı.']);
            }
            if (! $quote->isDraft()) {
                throw ValidationException::withMessages(['quote' => 'Yalnız taslak tekliften revizyon snapshot oluşturulabilir.']);
            }

            $quote->load(['account', 'lines.product', 'lines.tax', 'lines.taxZeroReason']);
            $account = $quote->account;
            if (! $account instanceof Account || $quote->lines->isEmpty()) {
                throw new LogicException('Revision snapshot requires a persisted account and at least one quote line.');
            }

            $quoteDate = $this->rawString($quote, 'quote_date');
            $validUntilRaw = $quote->getRawOriginal('valid_until');
            $validUntil = is_string($validUntilRaw) ? $validUntilRaw : null;
            $lineRows = [];

            foreach ($quote->lines as $line) {
                $product = $line->product;
                $tax = $line->tax;
                if (! $product instanceof Product || $tax === null) {
                    throw new LogicException('Revision snapshot requires persisted product and tax relations.');
                }

                $lineRows[] = [
                    'position' => (int) $line->position,
                    'product_id' => (int) $product->getKey(),
                    'product_code' => (string) $line->product_code,
                    'product_name' => (string) $product->name,
                    'description' => (string) $line->description,
                    'quantity' => $this->rawString($line, 'quantity'),
                    'price_basis' => $this->rawString($line, 'price_basis'),
                    'unit_price' => $this->rawString($line, 'unit_price'),
                    'line_discount_rate' => $this->rawString($line, 'line_discount_rate'),
                    'tax_id' => (int) $tax->getKey(),
                    'tax_code' => (string) $tax->code,
                    'tax_rate' => $this->rawString($line, 'tax_rate'),
                    'tax_zero_reason_id' => $line->tax_zero_reason_id === null ? null : (int) $line->tax_zero_reason_id,
                    'tax_zero_reason_code' => $line->tax_zero_reason_code === null ? null : (string) $line->tax_zero_reason_code,
                    'base_net' => $this->rawString($line, 'base_net'),
                    'line_discount_net' => $this->rawString($line, 'line_discount_net'),
                    'document_discount_net' => $this->rawString($line, 'document_discount_net'),
                    'net_total' => $this->rawString($line, 'net_total'),
                    'tax_total' => $this->rawString($line, 'tax_total'),
                    'gross_total' => $this->rawString($line, 'gross_total'),
                ];
            }

            $header = [
                'quote_number' => (string) $quote->number,
                'account_id' => (int) $account->getKey(),
                'account_code' => (string) $account->code,
                'account_name' => (string) $account->legal_name,
                'quote_date' => $quoteDate,
                'valid_until' => $validUntil,
                'currency_code' => (string) $quote->currency_code,
                'document_discount_rate' => $this->rawString($quote, 'document_discount_rate'),
                'base_net_total' => $this->rawString($quote, 'base_net_total'),
                'line_discount_total' => $this->rawString($quote, 'line_discount_total'),
                'document_discount_total' => $this->rawString($quote, 'document_discount_total'),
                'net_total' => $this->rawString($quote, 'net_total'),
                'tax_total' => $this->rawString($quote, 'tax_total'),
                'gross_total' => $this->rawString($quote, 'gross_total'),
                'note' => $quote->note === null ? null : (string) $quote->note,
            ];
            $fingerprint = hash('sha256', json_encode(
                ['header' => $header, 'lines' => $lineRows],
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));

            $existing = QuoteRevision::query()
                ->where('company_id', $companyId)
                ->where('quote_id', $quoteId)
                ->where('content_fingerprint', $fingerprint)
                ->first();
            if ($existing !== null) {
                return $existing->load('lines');
            }

            $lastRevision = QuoteRevision::query()
                ->where('company_id', $companyId)
                ->where('quote_id', $quoteId)
                ->orderByDesc('revision_number')
                ->value('revision_number');
            $revisionNumber = (is_int($lastRevision) || is_string($lastRevision))
                ? ((int) $lastRevision) + 1
                : 1;

            $revision = QuoteRevision::query()->create([
                'company_id' => $companyId,
                'quote_id' => $quoteId,
                'revision_number' => $revisionNumber,
                ...$header,
                'content_fingerprint' => $fingerprint,
            ]);

            foreach ($lineRows as $lineRow) {
                $revision->lines()->create([
                    'company_id' => $companyId,
                    ...$lineRow,
                ]);
            }

            $this->audit->record(
                AuditAction::QuoteRevisionCreated,
                AuditTargetType::QuoteRevision,
                $revision->getKey(),
                after: [
                    'quote_id' => $quoteId,
                    'revision_number' => $revisionNumber,
                    'content_fingerprint' => $fingerprint,
                ],
            );

            return $revision->load('lines');
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
