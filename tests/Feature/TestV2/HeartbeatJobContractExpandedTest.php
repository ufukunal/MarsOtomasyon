<?php

use App\Jobs\OperationsHeartbeatJob;
use App\Jobs\QueueHeartbeatJob;
use App\Models\OperationalHeartbeat;
use App\Support\Operations\OperationalHeartbeatService;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->createCompanyWithPeriod('V2HEARTBEAT');
});

it('v2 queue heartbeat writes a TTL to mocked Redis and persists a separate service heartbeat', function () {
    $client = Mockery::mock();
    $client->shouldReceive('setex')->once()->with('mars:queue-worker-heartbeat', 180, Mockery::type('string'));
    Redis::shouldReceive('connection')->once()->with('queue')->andReturn($client);

    (new QueueHeartbeatJob)->handle(app(OperationalHeartbeatService::class));
    $row = OperationalHeartbeat::query()->where('service_key', 'queue.default')->firstOrFail();
    expect($row->metadata['queue'])->toBe('default')
        ->and($row->metadata['connection'])->toBe('redis');
});

it('v2 operations heartbeat uses a separate queue and redis key from foreground workers', function () {
    $client = Mockery::mock();
    $client->shouldReceive('setex')->once()->with('mars:operations-worker-heartbeat', 180, Mockery::type('string'));
    Redis::shouldReceive('connection')->once()->with('queue')->andReturn($client);

    $job = new OperationsHeartbeatJob;
    expect($job->queue)->toBe('operations');
    $job->handle(app(OperationalHeartbeatService::class));

    $row = OperationalHeartbeat::query()->where('service_key', 'queue.operations')->firstOrFail();
    expect($row->metadata['queue'])->toBe('operations')
        ->and($row->metadata['connection'])->toBe('redis');
});
