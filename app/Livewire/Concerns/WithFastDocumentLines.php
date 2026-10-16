<?php

namespace App\Livewire\Concerns;

/**
 * Keep one unpersisted product-entry row at the end of editable documents.
 * The row is never sent to the document saver or included in VAT previews.
 */
trait WithFastDocumentLines
{
    /** @param array<string,mixed> $line */
    private function isUnusedEntryRow(array $line): bool
    {
        return ($line['line_kind'] ?? 'stock') === 'stock'
            && empty($line['product_id'])
            && trim((string) ($line['description'] ?? '')) === ''
            && empty($line['unit_id'])
            && empty($line['location_id'])
            && in_array(trim((string) ($line['quantity'] ?? '1')), ['1', '1.0', '1.000'], true)
            && in_array(trim((string) ($line['unit_price'] ?? '0')), ['', '0', '0.0', '0.0000'], true)
            && in_array(trim((string) ($line['line_discount_rate'] ?? '0')), ['0', '0.0', '0.0000'], true)
            && in_array(trim((string) ($line['line_discount_amount'] ?? '0')), ['0', '0.0', '0.0000'], true)
            && in_array(trim((string) ($line['vat_rate'] ?? '20')), ['0', '0.0', '0.0000', '20', '20.0', '20.0000'], true);
    }

    /** @return list<array<string,mixed>> */
    private function enteredLines(): array
    {
        return array_values(array_filter(
            $this->lines,
            fn (array $line): bool => ! $this->isUnusedEntryRow($line),
        ));
    }

    private function ensureNextEntryRow(): void
    {
        // Collapse empty rows after changing or clearing a product.
        // Keep partially filled rows so validation can show their errors.
        $entered = $this->enteredLines();
        $empty = null;
        foreach ($this->lines as $line) {
            if ($this->isUnusedEntryRow($line)) {
                $empty = $line;
            }
        }
        $entered[] = $empty ?? $this->emptyLine();
        $this->lines = $entered;
    }
}
