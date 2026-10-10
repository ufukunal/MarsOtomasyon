<?php

use App\Actions\Sales\ResolveSalesDueDate;
use App\Models\Period\Contact;

it('honors contact payment terms over month and leap-year boundaries', function (string $date, int $days, string $due): void {
    $contact = new Contact;
    $contact->term_days = $days;
    expect((new ResolveSalesDueDate)->handle($contact, $date))->toBe($due);
})->with([
    ['2026-10-10', 0, '2026-10-10'],
    ['2026-10-10', 30, '2026-11-09'],
    ['2024-02-28', 1, '2024-02-29'],
    ['2025-12-31', 1, '2026-01-01'],
]);
