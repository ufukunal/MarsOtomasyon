<?php

use App\Actions\Purchases\ApplySupplierInvoiceCosts;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use Illuminate\Database\Eloquent\Collection;

function marsV4DiscountFixture(string $discount): Document
{
    $invoice = new Document(['document_type' => 'supplier_invoice', 'subtotal' => '100.0000', 'discount_amount' => $discount]);
    $first = new DocumentLine(['line_total' => '60.0000']);
    $first->id = 101;
    $second = new DocumentLine(['line_total' => '40.0000']);
    $second->id = 102;
    $invoice->setRelation('lines', new Collection([$first, $second]));

    return $invoice;
}

it('splits document-level supplier discounts across line costs without losing rounding precision', function (): void {
    $subject = (new ReflectionClass(ApplySupplierInvoiceCosts::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(ApplySupplierInvoiceCosts::class, 'netLineAmounts');
    $rows = $method->invoke($subject, marsV4DiscountFixture('10.0000'));

    expect($rows)->toBe([101 => '54.00000000', 102 => '36.00000000'])
        ->and(bcadd($rows[101], $rows[102], 8))->toBe('90.00000000');
});

it('allocates the final rounding remainder to the last invoice line', function (): void {
    $subject = (new ReflectionClass(ApplySupplierInvoiceCosts::class))->newInstanceWithoutConstructor();
    $method = new ReflectionMethod(ApplySupplierInvoiceCosts::class, 'netLineAmounts');
    $rows = $method->invoke($subject, marsV4DiscountFixture('0.0001'));

    expect(bcadd($rows[101], $rows[102], 8))->toBe('99.99990000');
});
