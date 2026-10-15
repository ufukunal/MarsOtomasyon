<?php

use App\Actions\Documents\CalculateDocumentTotals;
use App\Livewire\Purchases\PurchaseOrderEditor;
use App\Livewire\Sales\SalesInvoiceEditor;
use Illuminate\Validation\ValidationException;

it('reallocates document discount over multiple VAT groups without mixing rates', function (): void {
    $result = app(CalculateDocumentTotals::class)->handle([
        ['quantity' => '2', 'unit_price' => '100', 'vat_rate' => '20'],
        ['quantity' => '1', 'unit_price' => '100', 'vat_rate' => '10'],
    ], '10');

    expect($result->subtotal)->toBe('300.0000')
        ->and($result->discountAmount)->toBe('30.0000')
        ->and($result->taxBase)->toBe('270.0000')
        ->and($result->vatAmount)->toBe('45.0000')
        ->and($result->grandTotal)->toBe('315.0000');
});

it('marks user-modified sales prices as manual and clears stale discount amounts', function (): void {
    $editor = new SalesInvoiceEditor;
    $editor->lines = [[
        'row_key' => 'test-1',
        'line_kind' => 'service',
        'description' => 'Test hizmet',
        'quantity' => '2',
        'unit_price' => '100',
        'line_discount_rate' => '5',
        'line_discount_amount' => '10.0000',
        'vat_rate' => '20',
        'price_source' => 'auto',
    ]];

    $editor->updated('lines.0.unit_price');

    expect($editor->lines[0]['price_source'])->toBe('manual')
        ->and($editor->lines[0]['line_discount_amount'])->toBe('0')
        ->and($editor->documentDirty)->toBeTrue();
});

it('clears persisted discount amounts when purchase quantity changes', function (): void {
    $editor = new PurchaseOrderEditor;
    $editor->lines = [[
        'row_key' => 'test-1',
        'line_kind' => 'service',
        'quantity' => '3',
        'unit_price' => '80',
        'line_discount_rate' => '5',
        'line_discount_amount' => '10',
        'vat_rate' => '20',
    ]];

    $editor->updated('lines.0.quantity');

    expect($editor->lines[0]['line_discount_amount'])->toBe('0')
        ->and($editor->documentDirty)->toBeTrue();
});

it('does not allow posting from a dirty document editor', function (): void {
    $editor = new SalesInvoiceEditor;
    $editor->documentDirty = true;

    $guard = new ReflectionMethod($editor, 'requireSavedDraft');

    expect(fn () => $guard->invoke($editor))
        ->toThrow(ValidationException::class);
});

it('uses the backend financial calculator for an unsaved draft preview', function (): void {
    $editor = new PurchaseOrderEditor;
    $editor->discountRate = '10';
    $editor->lines = [[
        'line_kind' => 'service',
        'quantity' => '2',
        'unit_price' => '100',
        'line_discount_rate' => '0',
        'line_discount_amount' => '0',
        'vat_rate' => '20',
    ]];

    $preview = $editor->draftTotalsPreview();

    expect($preview)->not->toBeNull()
        ->and($preview->taxBase)->toBe('180.0000')
        ->and($preview->vatAmount)->toBe('36.0000')
        ->and($preview->grandTotal)->toBe('216.0000');
});
