<?php

use App\Jobs\PollHepsiburadaWebhookJob;
use App\Jobs\PollWooCommerceWebhookJob;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;

it('deduplicates webhook polling by tenant, accounting period and channel account', function (string $class): void {
    $first = new $class(10, 2026, 500, 'order.created');
    $second = new $class(10, 2026, 500, 'order.updated');
    $otherPeriod = new $class(10, 2027, 500, 'order.updated');
    $otherCompany = new $class(20, 2026, 500, 'order.updated');

    expect($first)->toBeInstanceOf(ShouldQueue::class)
        ->toBeInstanceOf(ShouldBeUnique::class)
        ->and($first->uniqueId())->toBe($second->uniqueId())
        ->and($first->uniqueId())->not->toBe($otherPeriod->uniqueId())
        ->not->toBe($otherCompany->uniqueId())
        ->and($first->backoff())->toBe([30, 60, 120])
        ->and($first->tries)->toBe(4);
})->with([
    PollHepsiburadaWebhookJob::class,
    PollWooCommerceWebhookJob::class,
]);
