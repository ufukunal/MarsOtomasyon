<?php

namespace App\Modules\Inventory\Mobile;

use App\Foundation\Features\FeatureKey;
use App\Foundation\Features\FeatureRegistry;
use App\Modules\Core\Company\ActiveCompanyContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class MobileWarehouseController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private FeatureRegistry $features,
        private MobileWarehouseService $mobile,
    ) {}

    public function index(): View
    {
        $this->assertEnabled();

        return view('mobile.warehouse');
    }

    public function lookup(Request $request): JsonResponse
    {
        $this->assertEnabled();
        Gate::authorize('products.view');
        $validated = $request->validate(['scan' => ['required', 'string', 'max:160']]);

        return response()->json([
            'data' => $this->mobile->lookupProduct(
                (int) $this->companyContext->requireCompany()->getKey(),
                (string) $validated['scan'],
            ),
        ]);
    }

    public function execute(Request $request): JsonResponse
    {
        $this->assertEnabled();
        $validated = $request->validate([
            'operation_type' => ['required', 'string', 'max:80'],
            'payload' => ['required', 'array'],
        ]);
        $operationType = strtolower(trim((string) $validated['operation_type']));
        Gate::authorize($this->mobile->permissionFor($operationType));

        $clientId = trim((string) $request->header('X-Mobile-Client-ID', ''));
        $operationId = trim((string) $request->header('Idempotency-Key', ''));
        if ($clientId === '' || $operationId === '') {
            throw ValidationException::withMessages([
                'headers' => 'X-Mobile-Client-ID ve Idempotency-Key zorunludur.',
            ]);
        }

        $user = $request->user();
        $userId = $user === null ? null : (int) $user->getAuthIdentifier();
        /** @var array<string,mixed> $payload */
        $payload = $validated['payload'];
        $result = $this->mobile->execute(
            (int) $this->companyContext->requireCompany()->getKey(),
            $userId,
            $clientId,
            $operationId,
            $operationType,
            $payload,
        );

        return response()->json(['data' => $result]);
    }

    private function assertEnabled(): void
    {
        abort_unless($this->features->enabled(FeatureKey::MobileWarehouse), 404);
    }
}
