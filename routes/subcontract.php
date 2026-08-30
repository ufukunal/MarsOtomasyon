<?php

use App\Modules\Subcontract\SubcontractController;
use Illuminate\Support\Facades\Route;

Route::prefix('subcontract')->name('subcontract.')->middleware(['auth', 'company.context'])->group(function (): void {
    Route::get('/', [SubcontractController::class, 'index'])->middleware('can:subcontract.view')->name('index');
    Route::get('/report', [SubcontractController::class, 'report'])->middleware('can:subcontract.view')->name('report');
    Route::post('/', [SubcontractController::class, 'store'])->middleware('can:subcontract.manage')->name('store');
    Route::get('/{order}', [SubcontractController::class, 'show'])->whereNumber('order')->middleware('can:subcontract.view')->name('show');
    Route::post('/{order}/send', [SubcontractController::class, 'send'])->whereNumber('order')->middleware('can:subcontract.manage')->name('send');
    Route::post('/{order}/losses', [SubcontractController::class, 'loss'])->whereNumber('order')->middleware('can:subcontract.manage')->name('losses.store');
    Route::post('/{order}/receipts', [SubcontractController::class, 'receive'])->whereNumber('order')->middleware('can:subcontract.manage')->name('receipts.store');
    Route::post('/{order}/complete', [SubcontractController::class, 'complete'])->whereNumber('order')->middleware('can:subcontract.manage')->name('complete');
    Route::post('/{order}/files', [SubcontractController::class, 'upload'])->whereNumber('order')->middleware('can:subcontract.manage')->name('files.store');
    Route::get('/{order}/files/{attachment}', [SubcontractController::class, 'download'])->whereNumber('order')->whereNumber('attachment')->middleware('can:subcontract.view')->name('files.download');
    Route::post('/{order}/files/{attachment}/detach', [SubcontractController::class, 'detach'])->whereNumber('order')->whereNumber('attachment')->middleware('can:subcontract.manage')->name('files.detach');
});
