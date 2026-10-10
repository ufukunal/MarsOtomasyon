<?php

use App\Actions\Purchases\ResolvePurchaseDueDate;
use App\Models\Period\Contact;

it('resolves supplier payment terms without consulting another company', function (string $date, int $term, string $expected): void {
    $supplier = new Contact;
    $supplier->term_days = $term;
    expect((new ResolvePurchaseDueDate)->handle($supplier, $date))->toBe($expected);
})->with([
    ['2026-10-01', 15, '2026-10-16'],
    ['2025-12-20', 20, '2026-01-09'],
    ['2024-02-29', 365, '2025-02-28'],
]);
