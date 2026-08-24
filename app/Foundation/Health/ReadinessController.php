<?php

namespace App\Foundation\Health;

use App\Foundation\Correlation\CorrelationContext;
use Illuminate\Http\JsonResponse;

final readonly class ReadinessController
{
    public function __construct(
        private ReadinessCheck $readiness,
        private CorrelationContext $correlation,
    ) {}

    public function __invoke(): JsonResponse
    {
        $result = $this->readiness->check();

        return response()->json([
            'status' => $result->ready ? 'ok' : 'unavailable',
            'correlation_id' => $this->correlation->requireId(),
        ], $result->ready ? 200 : 503);
    }
}
