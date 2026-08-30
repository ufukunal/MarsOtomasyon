<?php

use App\Modules\Commerce\Http\CommerceController;
use App\Modules\Operations\Http\ChannelWebhookController;
use App\Modules\Operations\Http\OperationsController;
use App\Modules\Operations\RequirePlatformAdmin;
use Illuminate\Support\Facades\Route;

Route::post('/api/channels/{connection}/webhook', ChannelWebhookController::class)
    ->where('connection', '[0-9A-HJKMNP-TV-Z]{26}')
    ->middleware('throttle:120,1')
    ->name('channels.webhook');

Route::prefix('commerce')
    ->name('commerce.')
    ->middleware(['web', 'auth', 'company.context'])
    ->group(function (): void {
        Route::get('/', [CommerceController::class, 'index'])->middleware('can:integrations.view')->name('index');
        Route::post('/connections', [CommerceController::class, 'storeConnection'])->middleware('can:integrations.manage')->name('connections.store');
        Route::post('/connections/{connection}/test', [CommerceController::class, 'testConnection'])->where('connection', '[0-9A-HJKMNP-TV-Z]{26}')->middleware('can:integrations.manage')->name('connections.test');
        Route::post('/mappings', [CommerceController::class, 'storeMapping'])->middleware('can:integrations.manage')->name('mappings.store');
        Route::post('/publish', [CommerceController::class, 'publish'])->middleware('can:integrations.manage')->name('publish');
        Route::post('/orders/{order}/retry', [CommerceController::class, 'retryOrder'])->where('order', '[0-9A-HJKMNP-TV-Z]{26}')->middleware('can:integrations.manage')->name('orders.retry');
        Route::post('/returns', [CommerceController::class, 'storeReturn'])->middleware('can:integrations.manage')->name('returns.store');
        Route::post('/settlements', [CommerceController::class, 'storeSettlement'])->middleware('can:integrations.manage')->name('settlements.store');
        Route::post('/settlements/{settlement}/handoff', [CommerceController::class, 'handoffSettlement'])->where('settlement', '[0-9A-HJKMNP-TV-Z]{26}')->middleware('can:integrations.manage')->name('settlements.handoff');
    });

Route::get('/communications', fn () => redirect()->route('operations.index'))
    ->middleware(['auth', 'company.context', 'can:notifications.view'])
    ->name('communications.index');

Route::prefix('operations')
    ->name('operations.')
    ->middleware(['auth', 'company.context'])
    ->group(function (): void {
        Route::get('/', [OperationsController::class, 'index'])->middleware('can:operations.view')->name('index');
        Route::post('/connections', [OperationsController::class, 'storeConnection'])->middleware('can:integrations.manage')->name('connections.store');
        Route::post('/templates', [OperationsController::class, 'storeTemplate'])->middleware('can:notifications.manage')->name('templates.store');
        Route::post('/automation-rules', [OperationsController::class, 'storeAutomationRule'])->middleware('can:automation.manage')->name('automation-rules.store');
        Route::post('/automation-runs/{run}/approve', [OperationsController::class, 'approveAutomation'])->whereNumber('run')->middleware('can:automation.manage')->name('automation-runs.approve');
        Route::post('/automation-runs/{run}/reject', [OperationsController::class, 'rejectAutomation'])->whereNumber('run')->middleware('can:automation.manage')->name('automation-runs.reject');
        Route::post('/ip-rules', [OperationsController::class, 'storeIpRule'])->middleware('can:security.manage')->name('ip-rules.store');
        Route::delete('/ip-rules/{rule}', [OperationsController::class, 'destroyIpRule'])->whereNumber('rule')->middleware('can:security.manage')->name('ip-rules.destroy');
        Route::post('/backups', [OperationsController::class, 'createBackup'])->middleware([RequirePlatformAdmin::class, 'can:backups.manage'])->name('backups.create');
        Route::post('/backups/{backup}/verify', [OperationsController::class, 'verifyBackup'])->middleware([RequirePlatformAdmin::class, 'can:backups.view'])->name('backups.verify');
        Route::post('/backups/{backup}/restore', [OperationsController::class, 'restoreBackup'])->middleware([RequirePlatformAdmin::class, 'can:backups.manage'])->name('backups.restore');
        Route::post('/retry/{type}/{id}', [OperationsController::class, 'retry'])->whereIn('type', ['event', 'sync', 'notification', 'automation'])->whereNumber('id')->middleware('can:operations.manage')->name('retry');
        Route::get('/export/{type}', [OperationsController::class, 'exportCsv'])->middleware('can:operations.view')->name('export');
        Route::post('/import/{type}', [OperationsController::class, 'importCsv'])->middleware('can:operations.manage')->name('import');
    });
