<?php

namespace App\Foundation\Health;

use App\Foundation\Operations\ProductionCandidateGate;
use App\Foundation\Operations\ProductionSafetyState;
use Illuminate\Database\DatabaseManager;
use Illuminate\Redis\RedisManager;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class SystemReadinessCheck implements ReadinessCheck
{
    public function __construct(
        private DatabaseManager $database,
        private RedisManager $redis,
        private ProductionSafetyState $safety,
        private ?ProductionCandidateGate $candidateGate = null,
    ) {}

    public function check(): ReadinessResult
    {
        $failed = [];

        if ($this->safety->recoveryMode()) {
            $failed[] = 'recovery-mode';
        }

        if (app()->environment('production') && $this->candidateGate !== null && ! $this->candidateGate->satisfied()) {
            $failed[] = 'production-candidate';
        }

        try {
            $this->database->connection()->selectOne('SELECT 1 AS ready');
        } catch (Throwable $exception) {
            $failed[] = 'postgresql';
            $this->logFailure('postgresql', $exception);
        }

        try {
            $this->redis->connection('default')->command('ping');
        } catch (Throwable $exception) {
            $failed[] = 'valkey';
            $this->logFailure('valkey', $exception);
        }

        return new ReadinessResult($failed === [], array_values(array_unique($failed)));
    }

    private function logFailure(string $dependency, Throwable $exception): void
    {
        Log::warning('Readiness dependency unavailable.', [
            'dependency' => $dependency,
            'exception_class' => $exception::class,
        ]);
    }
}
