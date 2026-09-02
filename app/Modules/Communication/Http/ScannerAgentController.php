<?php

namespace App\Modules\Communication\Http;

use App\Modules\Communication\ScannerAgentService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ScannerAgentController
{
    public function __construct(private readonly ScannerAgentService $agents) {}

    public function issueEnrollment(Request $request): JsonResponse
    {
        $token = $request->attributes->get('api_access_token');
        if (! is_array($token)) {
            return response()->json(['error' => ['code' => 'AUTHENTICATION_REQUIRED']], 401);
        }

        return response()->json(['data' => $this->agents->issueEnrollmentToken((int) $token['company_id'])], 201);
    }

    public function enroll(Request $request): JsonResponse
    {
        try {
            $result = $this->agents->enroll((string) $request->input('enrollment_token', ''), (string) $request->input('name', ''));

            return response()->json(['data' => $result], 201);
        } catch (DomainException $exception) {
            return response()->json(['error' => ['code' => 'SCANNER_ENROLLMENT_FAILED', 'message' => $exception->getMessage()]], 422);
        }
    }

    public function heartbeat(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('scanner_agent');
        $capabilities = $request->input('capabilities', []);
        if (! is_array($agent) || ! is_array($capabilities)) {
            return response()->json(['error' => ['code' => 'VALIDATION_FAILED']], 422);
        }
        $this->agents->heartbeat((int) $agent['id'], $capabilities);

        return response()->json(['data' => ['ok' => true]]);
    }

    public function enqueue(Request $request, string $agent): JsonResponse
    {
        $token = $request->attributes->get('api_access_token');
        if (! is_array($token)) {
            return response()->json(['error' => ['code' => 'AUTHENTICATION_REQUIRED']], 401);
        }
        try {
            $publicId = $this->agents->enqueue(
                (int) $token['company_id'],
                $agent,
                (string) $request->input('operation', ''),
                is_array($request->input('payload')) ? $request->input('payload') : [],
                (string) $request->header('Idempotency-Key', ''),
            );

            return response()->json(['data' => ['public_id' => $publicId]], 202);
        } catch (DomainException $exception) {
            return response()->json(['error' => ['code' => 'SCANNER_JOB_REJECTED', 'message' => $exception->getMessage()]], 422);
        }
    }

    public function claim(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('scanner_agent');
        if (! is_array($agent)) {
            return response()->json(['error' => ['code' => 'SCANNER_AUTHENTICATION_REQUIRED']], 401);
        }

        return response()->json(['data' => $this->agents->claim((int) $agent['id'])]);
    }

    public function complete(Request $request, string $job): JsonResponse
    {
        $agent = $request->attributes->get('scanner_agent');
        $result = $request->input('result', []);
        if (! is_array($agent) || ! is_array($result)) {
            return response()->json(['error' => ['code' => 'VALIDATION_FAILED']], 422);
        }
        try {
            $this->agents->complete((int) $agent['id'], $job, $result);

            return response()->json(['data' => ['ok' => true]]);
        } catch (DomainException $exception) {
            return response()->json(['error' => ['code' => 'SCANNER_JOB_STATE_INVALID', 'message' => $exception->getMessage()]], 409);
        }
    }

    public function fail(Request $request, string $job): JsonResponse
    {
        $agent = $request->attributes->get('scanner_agent');
        if (! is_array($agent)) {
            return response()->json(['error' => ['code' => 'SCANNER_AUTHENTICATION_REQUIRED']], 401);
        }
        try {
            $this->agents->fail((int) $agent['id'], $job, (string) $request->input('error', 'Scanner job failed.'));

            return response()->json(['data' => ['ok' => true]]);
        } catch (DomainException $exception) {
            return response()->json(['error' => ['code' => 'SCANNER_JOB_STATE_INVALID', 'message' => $exception->getMessage()]], 409);
        }
    }
}
