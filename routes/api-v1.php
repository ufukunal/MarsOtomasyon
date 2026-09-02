<?php

use App\Modules\Communication\Http\ReferenceApiController;
use App\Modules\Communication\Http\ScannerAgentController;
use Illuminate\Support\Facades\Route;

Route::post('/scanner/enroll', [ScannerAgentController::class, 'enroll']);

Route::middleware(['scanner.auth'])->prefix('scanner/agent')->group(function (): void {
    Route::post('/heartbeat', [ScannerAgentController::class, 'heartbeat']);
    Route::post('/jobs/claim', [ScannerAgentController::class, 'claim']);
    Route::post('/jobs/{job}/complete', [ScannerAgentController::class, 'complete'])->where('job', '[0-9A-HJKMNP-TV-Z]{26}');
    Route::post('/jobs/{job}/fail', [ScannerAgentController::class, 'fail'])->where('job', '[0-9A-HJKMNP-TV-Z]{26}');
});

Route::middleware(['api.token', 'api.rate'])->group(function (): void {
    Route::get('/reference/ping', [ReferenceApiController::class, 'ping'])->middleware('api.permission:reference.read');
    Route::post('/reference/echo', [ReferenceApiController::class, 'echo'])->middleware(['api.permission:reference.write', 'api.idempotent']);
    Route::post('/scanner/enrollments', [ScannerAgentController::class, 'issueEnrollment'])->middleware('api.permission:scanner.manage');
    Route::post('/scanner/agents/{agent}/jobs', [ScannerAgentController::class, 'enqueue'])
        ->middleware('api.permission:scanner.manage')
        ->where('agent', '[0-9A-HJKMNP-TV-Z]{26}');
});
