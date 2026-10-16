<?php

namespace App\Support\Documents;

use App\Actions\Documents\CalculateDocumentTotals;
use App\Models\Period\Document;
use DomainException;

/**
 * Allocate a saved fixed document discount to selected (possibly partial) lines.
 *
 * Both the source and selection use their pre-document-discount net line total.
 * This prevents taking the full fixed discount for every partial delivery or
 * invoice. A full selection receives the exact original discount.
 */
final class ProrateFixedDiscount
{
    public function __construct(private readonly CalculateDocumentTotals $calculator) {}

    /**
     * @param  list<array<string,mixed>>  $selectedLines
     */
    public function handle(Document $source, array $selectedLines): string
    {
        $fixed = (string) ($source->additional_discount_amount ?? '0');
        if (bccomp($fixed, '0', 4) <= 0) {
            return '0.0000';
        }

        $base = (string) $source->subtotal;
        if (bccomp($base, '0', 4) <= 0) {
            throw new DomainException('Ek iskonto dağıtımı için kaynak satır toplamı pozitif olmalıdır.');
        }

        $subtotal = $this->calculator->handle($selectedLines)->subtotal;
        if (bccomp($subtotal, $base, 4) > 0) {
            throw new DomainException('Ek iskonto dağıtım tutarı kaynak belge satır toplamını aşıyor.');
        }
        if (bccomp($subtotal, $base, 4) === 0) {
            return bcadd($fixed, '0', 4);
        }

        return bcdiv(bcmul($fixed, $subtotal, 8), $base, 4);
    }
}
