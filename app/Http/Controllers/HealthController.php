<?php

namespace App\Http\Controllers;

use App\Support\Operations\OperationalHealthService;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    public function __invoke(OperationalHealthService $health): JsonResponse
    {
        $result = $health->check();

        return response()->json(
            $result,
            $result['status'] === 'healthy' ? 200 : 503,
        );
    }
}
