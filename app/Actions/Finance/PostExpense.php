<?php

namespace App\Actions\Finance;

use App\Actions\Documents\CalculateDocumentTotals;
use App\Actions\Documents\PostDocument;
use App\DataObjects\Documents\DocumentPostingContext;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use DomainException;

final class PostExpense
{
    public function __construct(
        private readonly CalculateDocumentTotals $calculator,
        private readonly PostDocument $postDocument,
    ) {}

    public function handle(
        string $category,
        string $netAmount,
        string $vatRate,
        string $documentDate,
        string $accountType,
        int $accountId,
        string $currency,
        string $exchangeRate,
        string $idempotencyKey,
        ?string $description = null,
    ): Document {
        MutationAuthorizer::authorize('expenses.create');

        return IdempotencyKey::run(
            $idempotencyKey,
            'expense.create',
            function () use (
                $category,
                $netAmount,
                $vatRate,
                $documentDate,
                $accountType,
                $accountId,
                $currency,
                $exchangeRate,
                $idempotencyKey,
                $description,
            ): Document {
                $category = trim($category);

                if ($category === '') {
                    throw new DomainException('Gider türü zorunludur.');
                }

                $totals = $this->calculator->handle([[
                    'quantity' => '1.000',
                    'unit_price' => bcadd($netAmount, '0', 4),
                    'line_discount_rate' => '0',
                    'line_discount_amount' => '0',
                    'vat_rate' => bcadd($vatRate, '0', 4),
                ]], '0', '0');

                $actor = auth()->user();
                $document = Document::query()->create([
                    'document_type' => DocumentType::Expense->value,
                    'revision_no' => 0,
                    'document_date' => $documentDate,
                    'currency' => strtoupper($currency),
                    'exchange_rate' => strtoupper($currency) === 'TRY'
                        ? '1.000000'
                        : bcadd($exchangeRate, '0', 6),
                    'status' => 'draft',
                    'discount_rate' => '0.0000',
                    'discount_amount' => $totals->discountAmount,
                    'subtotal' => $totals->subtotal,
                    'tax_base' => $totals->taxBase,
                    'vat_amount' => $totals->vatAmount,
                    'rounding_difference' => $totals->roundingDifference,
                    'grand_total' => $totals->grandTotal,
                    'requirements_snapshot' => ['expense_category' => $category],
                    'notes' => $description,
                    'created_by' => $actor?->id,
                    'created_by_name' => $actor?->name,
                ]);

                $line = $totals->lines[0];

                DocumentLine::query()->create([
                    'document_id' => $document->id,
                    'line_no' => 1,
                    'line_kind' => 'service',
                    'description' => $description ?: $category,
                    'quantity' => '1.000',
                    'unit_price' => bcadd($netAmount, '0', 4),
                    'line_discount_rate' => $line->discountRate,
                    'line_discount_amount' => $line->discountAmount,
                    'vat_rate' => bcadd($vatRate, '0', 4),
                    'line_total' => $line->lineTotal,
                    'reserve_stock' => false,
                    'cancelled_quantity' => '0.000',
                ]);

                return $this->postDocument->handle(
                    $document,
                    hash('sha256', $idempotencyKey.':post'),
                    new DocumentPostingContext(
                        accountType: $accountType,
                        accountId: $accountId,
                    ),
                );
            },
        );
    }
}
