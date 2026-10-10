<?php

use App\Jobs\CarryPeriodJob;
use App\Jobs\CreatePeriodJob;
use App\Jobs\GenerateReportExport;
use App\Jobs\OperationsHeartbeatJob;
use App\Jobs\PollHepsiburadaWebhookJob;
use App\Jobs\PollWooCommerceWebhookJob;
use App\Jobs\ProcessCardImport;
use App\Jobs\QueueHeartbeatJob;
use App\Jobs\RunRecoverySetBackupJob;
use App\Jobs\VerifyLatestRecoverySetBackupJob;
use App\Jobs\VerifyRecoverySetBackupJob;
use Illuminate\Contracts\Queue\ShouldQueue;

it('requires every production background worker job to implement the queue contract', function (string $class): void {
    expect(is_subclass_of($class, ShouldQueue::class))->toBeTrue()
        ->and((new ReflectionClass($class))->hasMethod('handle'))->toBeTrue();
})->with([
    [CarryPeriodJob::class],
    [CreatePeriodJob::class],
    [GenerateReportExport::class],
    [OperationsHeartbeatJob::class],
    [PollHepsiburadaWebhookJob::class],
    [PollWooCommerceWebhookJob::class],
    [ProcessCardImport::class],
    [QueueHeartbeatJob::class],
    [RunRecoverySetBackupJob::class],
    [VerifyLatestRecoverySetBackupJob::class],
    [VerifyRecoverySetBackupJob::class],
]);

it('keys period creation by company and year rather than actor identity', function (): void {
    $one = new CreatePeriodJob(11, 100, 2027);
    $same = new CreatePeriodJob(12, 100, 2027);
    $otherCompany = new CreatePeriodJob(11, 101, 2027);
    $otherYear = new CreatePeriodJob(11, 100, 2028);

    expect($one->uniqueId())->toBe($same->uniqueId())
        ->not->toBe($otherCompany->uniqueId())
        ->not->toBe($otherYear->uniqueId())
        ->and($one->timeout)->toBeGreaterThan(120)
        ->and($one->uniqueFor)->toBeGreaterThanOrEqual(120);
});

it('deduplicates period carry by source period and destination year', function (): void {
    $one = new CarryPeriodJob(3, 44, 2027, 'one');
    $replayed = new CarryPeriodJob(5, 44, 2027, 'two');
    $other = new CarryPeriodJob(3, 44, 2028, 'three');

    expect($one->uniqueId())->toBe($replayed->uniqueId())
        ->not->toBe($other->uniqueId())
        ->and($one->timeout)->toBeGreaterThanOrEqual(3600);
});

it('keeps bounded retries for export jobs without exposing credentials in payloads', function (): void {
    $job = new GenerateReportExport(
        exportJobId: 10,
        companyId: 20,
        periodId: 30,
        userId: 40,
        reportKey: 'sales.invoices',
        format: 'csv',
        filters: ['status' => 'posted'],
        columns: ['number', 'grand_total'],
        sort: [],
    );

    expect($job->tries)->toBe(3)
        ->and($job->backoff())->toBe([30, 120])
        ->and($job->companyId)->toBe(20)
        ->and($job->periodId)->toBe(30)
        ->and($job->filters)->toBe(['status' => 'posted']);
});

it('routes backup and verification jobs to the operations queue', function (): void {
    $backup = new RunRecoverySetBackupJob('manual');
    $verify = new VerifyRecoverySetBackupJob(55);
    $latest = new VerifyLatestRecoverySetBackupJob;

    expect($backup->queue)->toBe('operations')
        ->and($verify->queue)->toBe('operations')
        ->and($latest->queue)->toBe('operations')
        ->and($backup->timeout)->toBeGreaterThan(120);
});
