<?php

use App\Modules\Operations\Http\ChannelWebhookController;
use App\Modules\Operations\Http\OperationsController;
use Illuminate\Support\Facades\Route;

Route::post('/api/channels/{connection}/webhook', ChannelWebhookController::class)
    ->whereNumber('connection')
    ->middleware('throttle:120,1')
    ->name('channels.webhook');


Route::get('/commerce', fn () => redirect()->route('operations.index'))
    ->middleware(['auth', 'company.context', 'can:integrations.view'])
    ->name('commerce.index');
Route::get('/communications', fn () => redirect()->route('operations.index'))
    ->middleware(['auth', 'company.context', 'can:notifications.view'])
    ->name('communications.index');

Route::prefix('operations')
    ->name('operations.')
    ->middleware(['auth', 'company.context', 'security.ip'])
    ->group(function (): void {
        Route::get('/', [OperationsController::class, 'index'])->middleware('can:operations.view')->name('index');
        Route::post('/connections', [OperationsController::class, 'storeConnection'])->middleware('can:integrations.manage')->name('connections.store');
        Route::post('/templates', [OperationsController::class, 'storeTemplate'])->middleware('can:notifications.manage')->name('templates.store');
        Route::post('/automation-rules', [OperationsController::class, 'storeAutomationRule'])->middleware('can:automation.manage')->name('automation-rules.store');
        Route::post('/automation-runs/{run}/approve', [OperationsController::class, 'approveAutomation'])->whereNumber('run')->middleware('can:automation.manage')->name('automation-runs.approve');
        Route::post('/automation-runs/{run}/reject', [OperationsController::class, 'rejectAutomation'])->whereNumber('run')->middleware('can:automation.manage')->name('automation-runs.reject');
        Route::post('/ip-rules', [OperationsController::class, 'storeIpRule'])->middleware('can:security.manage')->name('ip-rules.store');
        Route::delete('/ip-rules/{rule}', [OperationsController::class, 'destroyIpRule'])->whereNumber('rule')->middleware('can:security.manage')->name('ip-rules.destroy');
        Route::post('/backups', [OperationsController::class, 'createBackup'])->middleware('can:backups.manage')->name('backups.create');
        Route::post('/backups/{backup}/verify', [OperationsController::class, 'verifyBackup'])->middleware('can:backups.view')->name('backups.verify');
        Route::post('/backups/{backup}/restore', [OperationsController::class, 'restoreBackup'])->middleware('can:backups.manage')->name('backups.restore');
        Route::post('/retry/{type}/{id}', [OperationsController::class, 'retry'])->whereIn('type', ['event', 'sync', 'notification', 'automation'])->whereNumber('id')->middleware('can:operations.manage')->name('retry');
        Route::get('/export/{type}', [OperationsController::class, 'exportCsv'])->middleware('can:operations.view')->name('export');
        Route::post('/import/{type}', [OperationsController::class, 'importCsv'])->middleware('can:operations.manage')->name('import');
    });
