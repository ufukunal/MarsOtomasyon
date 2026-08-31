<?php

namespace App\Modules\Communication\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ReferenceApiController
{
    public function ping(Request $request): JsonResponse
    {
        $token = $request->attributes->get('api_access_token');

        return response()->json(['data' => ['ok' => true, 'company_id' => is_array($token) ? (int) $token['company_id'] : null, 'version' => 'v1']]);
    }

    public function echo(Request $request): JsonResponse
    {
        $value = $request->input('value');
        if (! is_string($value) || trim($value) === '') {
            return response()->json(['error' => ['code' => 'VALIDATION_FAILED', 'message' => 'value must be a non-empty string.']], 422);
        }

        return response()->json(['data' => ['value' => $value]]);
    }
}
