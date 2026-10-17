<?php

namespace App\Support\Returns;

use App\Actions\Documents\CalculateDocumentTotals;
use App\Actions\Returns\ReturnLineAvailability;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Support\Documents\ProrateFixedDiscount;
use DomainException;

/**
 * Read-only iade grid preview. Never substitutes posting/stock validation.
 * Prices, VAT, unit and discounts originate exclusively from the invoice.
 */
final class ReturnDraftPreview
{
    public function __construct(
        private readonly CalculateDocumentTotals $calculator,
        private readonly ProrateFixedDiscount $discounts,
        private readonly ReturnLineAvailability $availability,
    ) {}

    /**
     * @param array<int, string> $quantities
     * @return array{totals: mixed, additional: string, rows: array<int, array{net: string, with_vat: string}>, remaining: array<int, string>, count: int, error: ?string}
     */
    public function handle(Document $invoice, DocumentType $returnType, array $quantities): array
    {
        $result = ['totals' => null, 'additional' => '0.0000', 'rows' => [],
            'remaining' => [], 'count' => 0, 'error' => null];

        $expectedType = $returnType === DocumentType::SalesReturn
            ? DocumentType::SalesInvoice : DocumentType::SupplierInvoice;
        if ($invoice->document_type !== $expectedType || $invoice->status !== 'posted') {
            $result['error'] = 'Seçilen kaynak fatura iade için uygun değil.';

            return $result;
        }

        $calculationRows = [];
        $lineIds = [];
        foreach ($invoice->lines as $line) {
            $id = (int) $line->id;
            $remaining = $this->availability->remaining($line, $returnType);
            $result['remaining'][$id] = $remaining;

            $quantity = trim((string) ($quantities[$id] ?? '0'));
            if ($quantity === '') {
                $quantity = '0';
            }
            if (! preg_match('/^\d{1,14}(?:\.\d{1,3})?$/D', $quantity)) {
                $result['error'] = 'İade miktarı en fazla üç ondalıklı, pozitif bir sayı olmalıdır.';

                return $result;
            }
            if (bccomp($quantity, '0', 3) <= 0) {
                continue;
            }
            if (bccomp($quantity, $remaining, 3) > 0) {
                $result['error'] = 'İade miktarı kalan kaynak fatura miktarını aşamaz.';

                return $result;
            }

            $calculationRows[] = [
                'quantity' => $quantity,
                'unit_price' => (string) $line->unit_price,
                'line_discount_rate' => (string) $line->line_discount_rate,
                'line_discount_amount' => '0',
                'vat_rate' => (string) $line->vat_rate,
            ];
            $lineIds[] = $id;
        }

        if ($calculationRows === []) {
            return $result;
        }

        try {
            $additional = $this->discounts->handle($invoice, $calculationRows);
            $totals = $this->calculator->handle(
                $calculationRows,
                (string) $invoice->discount_rate,
                '0',
                $additional,
            );

            foreach ($lineIds as $i => $id) {
                $net = (string) $totals->lines[$i]->lineTotal;
                $vatRate = (string) $calculationRows[$i]['vat_rate'];
                $vat = \App\Support\Money\Money::of($net)->percent($vatRate)->amount;
                $result['rows'][$id] = [
                    'net' => $net,
                    // Informational per-line VAT; document discounts are distributed at total level.
                    'with_vat' => \App\Support\Money\Money::of($net)
                        ->plus(\App\Support\Money\Money::of($vat))->amount,
                ];
            }
            $result['totals'] = $totals;
            $result['additional'] = $additional;
            $result['count'] = count($lineIds);
        } catch (DomainException $exception) {
            $result['error'] = $exception->getMessage();
        }

        return $result;
    }
}
