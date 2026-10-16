<?php

use App\Livewire\Concerns\WithFastDocumentLines;

it('filters the untouched trailing quick-entry row but never discards partially entered data', function (): void {
    $harness = new class
    {
        use WithFastDocumentLines {
            enteredLines as public;
            ensureNextEntryRow as public;
        }

        public array $lines = [];
        private int $n = 0;

        private function emptyLine(): array
        {
            return [
                'row_key' => 'new-'.(++$this->n),
                'line_kind' => 'stock',
                'product_id' => null,
                'description' => '',
                'unit_id' => null,
                'location_id' => null,
                'quantity' => '1.000',
                'unit_price' => '0',
                'line_discount_rate' => '0',
                'line_discount_amount' => '0',
                'vat_rate' => '20',
            ];
        }
    };

    $harness->ensureNextEntryRow();
    expect($harness->lines)->toHaveCount(1)
        ->and($harness->enteredLines())->toBe([]);

    $harness->lines[0]['product_id'] = 42;
    $harness->ensureNextEntryRow();
    expect($harness->lines)->toHaveCount(2)
        ->and($harness->enteredLines())->toHaveCount(1);

    $harness->ensureNextEntryRow();
    expect($harness->lines)->toHaveCount(2);

    $harness->lines[1]['quantity'] = '5.000';
    expect($harness->enteredLines())->toHaveCount(2);

    $harness->lines[1]['quantity'] = '1.000';
    $harness->lines[1]['line_kind'] = 'service';
    expect($harness->enteredLines())->toHaveCount(2);
});
