<?php

use App\Actions\Finance\PostCollection;
use App\Actions\Finance\ReverseFinanceTransfer;
use App\Actions\Imports\CloseImportFile;
use App\Actions\Imports\ReceiveImportFile;
use App\Actions\Imports\SaveImportFile;
use App\Actions\Production\CancelProductionOrderRemaining;
use App\Actions\Production\ConfirmProductionOrder;
use App\Actions\Production\SaveProductionOrderDraft;
use App\Actions\Purchases\PostGoodsReceipt;
use App\Actions\Purchases\PostPayment;
use App\Actions\Returns\CreateReturnFromInvoice;
use App\Actions\Returns\ReverseReturnDocument;
use App\Actions\Sales\CancelSalesOrderRemaining;
use App\Actions\Sales\ConfirmSalesOrder;
use App\Actions\Sales\ConvertProformaToInvoice;
use App\Actions\Sales\IssueProforma;
use App\Actions\Sales\PostDispatch;
use App\Actions\Sales\PostSalesInvoice;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\ImportFile;
use App\Models\Period\ProductionOrder;
use Illuminate\Auth\Access\AuthorizationException;

it('v2 sensitive operations reject anonymous actors before business validation or database mutations', function () {
    $document = new Document;
    $productionOrder = new ProductionOrder;
    $importFile = new ImportFile;
    $cases = [
        'post sales invoice' => fn () => app(PostSalesInvoice::class)->handle($document, 'v2-auth-sales'),
        'post dispatch' => fn () => app(PostDispatch::class)->handle($document, 'v2-auth-dispatch'),
        'confirm sales order' => fn () => app(ConfirmSalesOrder::class)->handle($document, 'v2-auth-confirm'),
        'cancel sales order' => fn () => app(CancelSalesOrderRemaining::class)->handle($document, 'v2-auth-cancel'),
        'issue proforma' => fn () => app(IssueProforma::class)->handle($document, '2026-09-01', 'v2-auth-proforma'),
        'convert proforma' => fn () => app(ConvertProformaToInvoice::class)->handle($document, [], '2026-09-01', 'v2-auth-proforma-convert'),
        'post receipt' => fn () => app(PostGoodsReceipt::class)->handle($document, 'v2-auth-receipt'),
        'post supplier payment' => fn () => app(PostPayment::class)->handle(1, '5', 'TRY', '1', '2026-09-01', 'cash', 1, 'v2-auth-pay'),
        'post collection' => fn () => app(PostCollection::class)->handle(1, '5', '2026-09-01', 'cash', 1, 'v2-auth-collect'),
        'reverse transfer' => fn () => app(ReverseFinanceTransfer::class)->handle('demo', '2026-09-01', 'Correction', 'v2-auth-reverse'),
        'create return' => fn () => app(CreateReturnFromInvoice::class)->handle($document, DocumentType::SalesReturn, [], [], '2026-09-01', 'v2-auth-create-return'),
        'reverse return' => fn () => app(ReverseReturnDocument::class)->handle($document, '2026-09-01', 'Correction', 'v2-auth-reverse-return'),
        'save import' => fn () => app(SaveImportFile::class)->handle([]),
        'receive import' => fn () => app(ReceiveImportFile::class)->handle($importFile, '2026-09-01', 'v2-auth-import-receive'),
        'close import' => fn () => app(CloseImportFile::class)->handle($importFile, 'v2-auth-import-close'),
        'save production' => fn () => app(SaveProductionOrderDraft::class)->handle([]),
        'confirm production' => fn () => app(ConfirmProductionOrder::class)->handle($productionOrder, 'v2-auth-production-confirm'),
        'cancel production' => fn () => app(CancelProductionOrderRemaining::class)->handle($productionOrder, 'v2-auth-production-cancel'),
    ];

    foreach ($cases as $name => $call) {
        try {
            $call();
            $this->fail("{$name}: unauthorized mutation returned successfully");
        } catch (AuthorizationException $exception) {
            expect($exception->getMessage())->not->toBe('');
        }
    }
});