<?php

use App\Modules\Core\Management\CadPreviewController;
use Illuminate\Support\Facades\Route;

Route::prefix('settings/files')
    ->name('settings.files.cad.')
    ->middleware(['web', 'auth', 'company.context'])
    ->group(function (): void {
        Route::get('/cad-policy', [CadPreviewController::class, 'policy'])
            ->middleware('can:core.file.manage')
            ->name('policy');
        Route::put('/cad-policy', [CadPreviewController::class, 'updatePolicy'])
            ->middleware('can:core.file.manage')
            ->name('policy.update');

        Route::get('/{attachment}/cad', [CadPreviewController::class, 'show'])
            ->whereNumber('attachment')
            ->middleware('can:core.file.view')
            ->name('show');
        Route::post('/{attachment}/cad', [CadPreviewController::class, 'request'])
            ->whereNumber('attachment')
            ->middleware('can:core.file.view')
            ->name('request');
        Route::get('/{attachment}/cad/source', [CadPreviewController::class, 'source'])
            ->whereNumber('attachment')
            ->middleware('can:core.file.view')
            ->name('source');
        Route::post('/{attachment}/cad/{job}/refresh', [CadPreviewController::class, 'refresh'])
            ->whereNumber('attachment')
            ->whereNumber('job')
            ->middleware('can:core.file.view')
            ->name('refresh');
        Route::post('/{attachment}/cad/{job}/rebuild', [CadPreviewController::class, 'rebuild'])
            ->whereNumber('attachment')
            ->whereNumber('job')
            ->middleware('can:core.file.manage')
            ->name('rebuild');
        Route::get('/{attachment}/cad/{job}/viewer-token', [CadPreviewController::class, 'viewerToken'])
            ->whereNumber('attachment')
            ->whereNumber('job')
            ->middleware('can:core.file.view')
            ->name('viewer-token');
    });
