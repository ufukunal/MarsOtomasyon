<?php

use App\Modules\Instruments\InstrumentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'company.context'])->prefix('instruments')->name('instruments.')->group(function (): void {
    Route::get('/', [InstrumentController::class, 'index'])->middleware('can:instruments.view')->name('index');
    Route::post('/', [InstrumentController::class, 'store'])->middleware('can:instruments.manage')->name('store');
    Route::get('/{instrument}', [InstrumentController::class, 'show'])->whereNumber('instrument')->middleware('can:instruments.view')->name('show');
    Route::post('/{instrument}/send-to-bank', [InstrumentController::class, 'sendToBank'])->whereNumber('instrument')->middleware('can:instruments.manage')->name('send-to-bank');
    Route::post('/{instrument}/recall-from-bank', [InstrumentController::class, 'recallFromBank'])->whereNumber('instrument')->middleware('can:instruments.manage')->name('recall-from-bank');
    Route::post('/{instrument}/endorse', [InstrumentController::class, 'endorse'])->whereNumber('instrument')->middleware('can:instruments.manage')->name('endorse');
    Route::post('/{instrument}/settle', [InstrumentController::class, 'settle'])->whereNumber('instrument')->middleware('can:instruments.manage')->name('settle');
    Route::post('/{instrument}/dishonor', [InstrumentController::class, 'dishonor'])->whereNumber('instrument')->middleware('can:instruments.manage')->name('dishonor');
    Route::post('/{instrument}/return', [InstrumentController::class, 'returnToCounterparty'])->whereNumber('instrument')->middleware('can:instruments.manage')->name('return');
    Route::post('/{instrument}/cancel', [InstrumentController::class, 'cancel'])->whereNumber('instrument')->middleware('can:instruments.manage')->name('cancel');
    Route::post('/{instrument}/files', [InstrumentController::class, 'upload'])->whereNumber('instrument')->middleware('can:instruments.manage')->name('files.upload');
    Route::get('/{instrument}/files/{attachment}', [InstrumentController::class, 'download'])->whereNumber(['instrument', 'attachment'])->middleware('can:instruments.view')->name('files.download');
    Route::delete('/{instrument}/files/{attachment}', [InstrumentController::class, 'detach'])->whereNumber(['instrument', 'attachment'])->middleware('can:instruments.manage')->name('files.detach');
});
