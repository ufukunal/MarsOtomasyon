<?php

use App\Modules\SalesInvoices\SalesInvoiceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'company.context'])
    ->prefix('sales-invoices')
    ->name('sales-invoices.')
    ->group(function (): void {
        Route::get('/', [SalesInvoiceController::class, 'index'])->middleware('can:sales_invoices.view')->name('index');
        Route::get('/create', [SalesInvoiceController::class, 'create'])->middleware('can:sales_invoices.manage')->name('create');
        Route::post('/', [SalesInvoiceController::class, 'store'])->middleware('can:sales_invoices.manage')->name('store');
        Route::get('/{salesInvoice}', [SalesInvoiceController::class, 'show'])
            ->whereNumber('salesInvoice')
            ->middleware('can:sales_invoices.view')
            ->name('show');
    });
