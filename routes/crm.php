<?php

use App\Modules\Accounts\Crm\CrmController;
use Illuminate\Support\Facades\Route;

Route::prefix('customers/crm')
    ->name('crm.')
    ->middleware(['web', 'auth', 'company.context'])
    ->group(function (): void {
        Route::get('/', [CrmController::class, 'index'])->middleware('can:crm.view')->name('index');
        Route::post('/leads', [CrmController::class, 'storeLead'])->middleware('can:crm.manage')->name('leads.store');
        Route::post('/opportunities', [CrmController::class, 'storeOpportunity'])->middleware('can:crm.manage')->name('opportunities.store');
        Route::patch('/opportunities/{opportunity}/stage', [CrmController::class, 'moveStage'])->whereNumber('opportunity')->middleware('can:crm.manage')->name('opportunities.stage');
        Route::patch('/opportunities/{opportunity}/links', [CrmController::class, 'updateCommercialLinks'])->whereNumber('opportunity')->middleware('can:crm.manage')->name('opportunities.links');
        Route::post('/leads/{lead}/convert', [CrmController::class, 'convertLead'])->whereNumber('lead')->middleware('can:crm.manage')->name('leads.convert');
        Route::post('/activities', [CrmController::class, 'storeActivity'])->middleware('can:crm.manage')->name('activities.store');
        Route::post('/leads/{lead}/files', [CrmController::class, 'uploadLeadFile'])->whereNumber('lead')->middleware('can:crm.manage')->name('leads.files.store');
        Route::get('/leads/{lead}/files/{attachment}', [CrmController::class, 'downloadLeadFile'])->whereNumber('lead')->whereNumber('attachment')->middleware('can:crm.view')->name('leads.files.download');
        Route::post('/opportunities/{opportunity}/files', [CrmController::class, 'uploadOpportunityFile'])->whereNumber('opportunity')->middleware('can:crm.manage')->name('opportunities.files.store');
        Route::get('/opportunities/{opportunity}/files/{attachment}', [CrmController::class, 'downloadOpportunityFile'])->whereNumber('opportunity')->whereNumber('attachment')->middleware('can:crm.view')->name('opportunities.files.download');
    });
