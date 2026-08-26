<?php

namespace App\Modules\SalesInvoices\Actions;

use App\Modules\SalesInvoices\Models\SalesInvoiceOrderLineCapacity;
use Illuminate\Validation\ValidationException;

final class AssertSalesInvoiceOrderCapacity
{
    public function assert(int $companyId, ResolvedSalesInvoiceSource $source): void
    {
        $lineIds = [];
        foreach ($source->lines as $line) {
            if ($line->sourceSalesOrderLineId !== null) {
                $lineIds[] = $line->sourceSalesOrderLineId;
            }
        }

        if ($lineIds === []) {
            return;
        }

        $lineIds = array_values(array_unique($lineIds));
        $capacities = SalesInvoiceOrderLineCapacity::query()
            ->where('company_id', $companyId)
            ->whereIn('sales_order_line_id', $lineIds)
            ->get()
            ->keyBy('sales_order_line_id');

        if ($capacities->count() !== count($lineIds)) {
            throw ValidationException::withMessages([
                'lines' => 'Sipariş satırı faturalama kapasitesi hesaplanamadı.',
            ]);
        }

        foreach ($source->lines as $index => $line) {
            if ($line->sourceSalesOrderLineId === null) {
                continue;
            }

            $capacity = $capacities->get($line->sourceSalesOrderLineId);
            if (! $capacity instanceof SalesInvoiceOrderLineCapacity
                || $this->greaterThan($line->quantity, (string) $capacity->getAttribute('remaining_quantity'))) {
                throw ValidationException::withMessages([
                    "lines.$index.quantity" => 'Fatura miktarı sipariş satırının kalan faturalama kapasitesini aşamaz.',
                ]);
            }
        }
    }

    private function greaterThan(string $left, string $right): bool
    {
        if (str_starts_with($right, '-')) {
            return true;
        }

        $leftScaled = $this->scaledDigits($left);
        $rightScaled = $this->scaledDigits($right);

        if (strlen($leftScaled) !== strlen($rightScaled)) {
            return strlen($leftScaled) > strlen($rightScaled);
        }

        return strcmp($leftScaled, $rightScaled) > 0;
    }

    private function scaledDigits(string $value): string
    {
        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $digits = ltrim($integer.str_pad($fraction, 6, '0'), '0');

        return $digits === '' ? '0' : $digits;
    }
}
