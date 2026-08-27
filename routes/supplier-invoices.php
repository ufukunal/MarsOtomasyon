<?php

use App\Modules\SupplierInvoices\SupplierInvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'company.context'])
    ->prefix('supplier-invoices')
    ->name('supplier-invoices.')
    ->group(function (): void {
        Route::get('/', [SupplierInvoiceController::class, 'index'])->middleware('can:supplier_invoices.view')->name('index');
        Route::get('/create', [SupplierInvoiceController::class, 'create'])->middleware('can:supplier_invoices.manage')->name('create');
        Route::post('/', [SupplierInvoiceController::class, 'store'])->middleware('can:supplier_invoices.manage')->name('store');
        Route::get('/{supplierInvoice}', [SupplierInvoiceController::class, 'show'])->whereNumber('supplierInvoice')->middleware('can:supplier_invoices.view')->name('show');
        Route::get('/{supplierInvoice}/edit', [SupplierInvoiceController::class, 'edit'])->whereNumber('supplierInvoice')->middleware('can:supplier_invoices.manage')->name('edit');
        Route::put('/{supplierInvoice}', [SupplierInvoiceController::class, 'update'])->whereNumber('supplierInvoice')->middleware('can:supplier_invoices.manage')->name('update');
        Route::post('/{supplierInvoice}/finalize', [SupplierInvoiceController::class, 'finalize'])->whereNumber('supplierInvoice')->middleware('can:supplier_invoices.manage')->name('finalize');
    });
