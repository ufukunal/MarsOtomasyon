<?php

namespace App\Modules\Communication\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class IdempotentApiWrite
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header('Idempotency-Key', '');
        if (! Str::isUuid($key)) {
            return new JsonResponse(['error' => ['code' => 'IDEMPOTENCY_KEY_REQUIRED', 'message' => 'A UUID Idempotency-Key header is required.']], 422);
        }
        $token = $request->attributes->get('api_access_token');
        if (! is_array($token)) {
            return new JsonResponse(['error' => ['code' => 'AUTHENTICATION_REQUIRED', 'message' => 'Valid API bearer token required.']], 401);
        }
        $tokenId = (int) $token['id'];
        $companyId = (int) $token['company_id'];
        $payload = json_encode($request->all(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $requestHash = hash('sha256', $request->method().'|'.$request->path().'|'.$payload);

        $existing = DB::table('api_idempotency_keys')->where('api_access_token_id', $tokenId)->where('idempotency_key', $key)->first();
        if ($existing !== null) {
            if (! hash_equals((string) $existing->request_hash, $requestHash)) {
                return new JsonResponse(['error' => ['code' => 'IDEMPOTENCY_PAYLOAD_DRIFT', 'message' => 'Idempotency key was already used with different payload.']], 409);
            }
            if ((string) $existing->status === 'completed' && $existing->response_status !== null) {
                $decoded = $existing->response_body === null ? null : json_decode((string) $existing->response_body, true, flags: JSON_THROW_ON_ERROR);

                return new JsonResponse($decoded, (int) $existing->response_status, ['Idempotent-Replay' => 'true']);
            }
            return new JsonResponse(['error' => ['code' => 'IDEMPOTENCY_IN_PROGRESS', 'message' => 'Request with this idempotency key is still processing.']], 409);
        }

        DB::table('api_idempotency_keys')->insert([
            'company_id' => $companyId,
            'api_access_token_id' => $tokenId,
            'idempotency_key' => $key,
            'request_hash' => $requestHash,
            'status' => 'processing',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $next($request);
        if ($response->getStatusCode() < 500) {
            DB::table('api_idempotency_keys')->where('api_access_token_id', $tokenId)->where('idempotency_key', $key)->update([
                'status' => 'completed',
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            DB::table('api_idempotency_keys')->where('api_access_token_id', $tokenId)->where('idempotency_key', $key)->delete();
        }

        return $response;
    }
}
