<?php

use App\Modules\Imports\ImportController;
use Illuminate\Support\Facades\Route;

Route::prefix('imports')->name('import.')->middleware(['auth', 'company.context'])->group(function (): void {
    Route::get('/', [ImportController::class, 'index'])->middleware('can:imports.view')->name('index');
    Route::get('/report', [ImportController::class, 'report'])->middleware('can:imports.view')->name('report');
    Route::post('/', [ImportController::class, 'store'])->middleware('can:imports.manage')->name('store');
    Route::get('/{file}', [ImportController::class, 'show'])->whereNumber('file')->middleware('can:imports.view')->name('show');
    Route::post('/{file}/containers', [ImportController::class, 'container'])->whereNumber('file')->middleware('can:imports.manage')->name('containers.store');
    Route::post('/{file}/items', [ImportController::class, 'item'])->whereNumber('file')->middleware('can:imports.manage')->name('items.store');
    Route::post('/{file}/expenses', [ImportController::class, 'expense'])->whereNumber('file')->middleware('can:imports.manage')->name('expenses.store');
    Route::post('/{file}/expenses/{expense}/finalize', [ImportController::class, 'finalizeExpense'])->whereNumber('file')->whereNumber('expense')->middleware('can:imports.manage')->name('expenses.finalize');
    Route::post('/{file}/in-transit', [ImportController::class, 'inTransit'])->whereNumber('file')->middleware('can:imports.manage')->name('in-transit');
    Route::post('/{file}/arrived', [ImportController::class, 'arrived'])->whereNumber('file')->middleware('can:imports.manage')->name('arrived');
    Route::post('/{file}/receipt-links', [ImportController::class, 'receiptLink'])->whereNumber('file')->middleware('can:imports.manage')->name('receipt-links.store');
    Route::post('/{file}/landed-cost', [ImportController::class, 'landedCost'])->whereNumber('file')->middleware('can:imports.manage')->name('landed-cost');
    Route::post('/{file}/complete', [ImportController::class, 'complete'])->whereNumber('file')->middleware('can:imports.manage')->name('complete');
    Route::get('/{file}/picking-list', [ImportController::class, 'pickingList'])->whereNumber('file')->middleware('can:imports.view')->name('picking-list');
    Route::get('/{file}/subcontract-list', [ImportController::class, 'subcontractList'])->whereNumber('file')->middleware('can:imports.view')->name('subcontract-list');
    Route::get('/{file}/loading-simulator', [ImportController::class, 'loadingSimulator'])->whereNumber('file')->middleware('can:imports.view')->name('loading-simulator');
});
