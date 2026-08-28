<?php

use App\Modules\Treasury\TreasuryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'company.context'])
    ->prefix('treasury')
    ->name('treasury.')
    ->group(function (): void {
        Route::get('/', [TreasuryController::class, 'index'])
            ->middleware('can:treasury.view')
            ->name('index');

        Route::post('/accounts', [TreasuryController::class, 'storeAccount'])
            ->middleware('can:treasury.manage')
            ->name('accounts.store');
        Route::post('/methods', [TreasuryController::class, 'storeMethod'])
            ->middleware('can:treasury.manage')
            ->name('methods.store');

        Route::post('/payments', [TreasuryController::class, 'storePayment'])
            ->middleware('can:treasury.manage')
            ->name('payments.store');
        Route::post('/payments/{payment}/finalize', [TreasuryController::class, 'finalizePayment'])
            ->whereNumber('payment')
            ->middleware('can:treasury.manage')
            ->name('payments.finalize');
        Route::post('/payments/{payment}/reverse', [TreasuryController::class, 'reversePayment'])
            ->whereNumber('payment')
            ->middleware('can:treasury.manage')
            ->name('payments.reverse');
        Route::post('/payments/{payment}/settle-pos', [TreasuryController::class, 'settlePos'])
            ->whereNumber('payment')
            ->middleware('can:treasury.manage')
            ->name('payments.settle-pos');
        Route::post('/payments/{payment}/chargeback', [TreasuryController::class, 'chargebackPos'])
            ->whereNumber('payment')
            ->middleware('can:treasury.manage')
            ->name('payments.chargeback');

        Route::post('/manual-movements', [TreasuryController::class, 'storeManualMovement'])
            ->middleware('can:treasury.manage')
            ->name('manual-movements.store');
        Route::post('/manual-movements/{movement}/finalize', [TreasuryController::class, 'finalizeManualMovement'])
            ->whereNumber('movement')
            ->middleware('can:treasury.manage')
            ->name('manual-movements.finalize');

        Route::post('/transfers', [TreasuryController::class, 'storeTransfer'])
            ->middleware('can:treasury.manage')
            ->name('transfers.store');
        Route::post('/transfers/{transfer}/finalize', [TreasuryController::class, 'finalizeTransfer'])
            ->whereNumber('transfer')
            ->middleware('can:treasury.manage')
            ->name('transfers.finalize');

        Route::post('/expenses', [TreasuryController::class, 'storeExpense'])
            ->middleware('can:treasury.manage')
            ->name('expenses.store');
        Route::post('/expenses/{expense}/finalize', [TreasuryController::class, 'finalizeExpense'])
            ->whereNumber('expense')
            ->middleware('can:treasury.manage')
            ->name('expenses.finalize');

        Route::post('/cash-counts', [TreasuryController::class, 'storeCashCount'])
            ->middleware('can:treasury.manage')
            ->name('cash-counts.store');
        Route::post('/cash-counts/{count}/finalize', [TreasuryController::class, 'finalizeCashCount'])
            ->whereNumber('count')
            ->middleware('can:treasury.manage')
            ->name('cash-counts.finalize');

        Route::post('/statements/import', [TreasuryController::class, 'importStatement'])
            ->middleware('can:treasury.reconcile')
            ->name('statements.import');
        Route::post('/statements/{line}/match', [TreasuryController::class, 'matchStatement'])
            ->whereNumber('line')
            ->middleware('can:treasury.reconcile')
            ->name('statements.match');
        Route::post('/statements/{line}/ignore', [TreasuryController::class, 'ignoreStatement'])
            ->whereNumber('line')
            ->middleware('can:treasury.reconcile')
            ->name('statements.ignore');
    });
