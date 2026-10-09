<?php

use App\Actions\Finance\PostFinanceTransfer;
use App\Actions\Imports\RecalculateImportCosts;
use App\Actions\Production\PostProductionCompletion;
use App\Actions\Purchases\ApprovePurchaseOrder;
use App\Actions\Purchases\PostSupplierInvoice;
use App\Actions\Returns\PostReturnDocument;
use App\Models\Period\Document;
use App\Models\Period\ImportFile;
use App\Models\Period\ProductionOrder;
use Illuminate\Auth\Access\AuthorizationException;

it('v2 sensitive mutation actions fail closed without an authenticated user or system actor', function () {
    $attempts = [
        'purchase approval' => fn () => app(ApprovePurchaseOrder::class)->handle(new Document, 'v2-purchase'),
        'supplier invoice posting' => fn () => app(PostSupplierInvoice::class)->handle(new Document, 'v2-invoice'),
        'return posting' => fn () => app(PostReturnDocument::class)->handle(new Document, 'v2-return'),
        'finance transfer' => fn () => app(PostFinanceTransfer::class)->handle(
            'cash', 1, 'bank', 2, '10.0000', '2026-10-01', 'v2-finance',
        ),
        'import cost allocation' => fn () => app(RecalculateImportCosts::class)->handle(new ImportFile, 'v2-import'),
        'production completion' => fn () => app(PostProductionCompletion::class)->handle(
            new ProductionOrder, '2026-10-01', '1.000', [], [], 'v2-production',
        ),
    ];

    foreach ($attempts as $name => $attempt) {
        try {
            $attempt();
            $this->fail("{$name}: anonymous mutation must be rejected before any write.");
        } catch (AuthorizationException $exception) {
            expect($exception->getMessage())->not->toBe('');
        }
    }
});
