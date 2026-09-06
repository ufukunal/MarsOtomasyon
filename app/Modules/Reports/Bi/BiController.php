<?php

namespace App\Modules\Reports\Bi;

use App\Foundation\Features\FeatureKey;
use App\Foundation\Features\FeatureRegistry;
use App\Modules\Core\Branch\ActiveBranchContext;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\PermissionKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

final readonly class BiController
{
    public function __construct(
        private FeatureRegistry $features,
        private ActiveCompanyContext $companyContext,
        private ActiveBranchContext $branchContext,
        private BiDatasetRegistry $datasets,
        private BiExportService $exports,
    ) {}

    public function catalog(): JsonResponse
    {
        $this->guardFeature();

        return response()->json(['datasets' => $this->datasets->catalog()]);
    }

    public function export(Request $request, string $datasetKey): Response
    {
        $this->guardFeature();
        abort_unless(Gate::allows(PermissionKey::ReportsBiExport->value), 403);

        $validated = $request->validate([
            'format' => ['required', 'in:csv,json'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['required', 'string', 'max:128'],
            'watermark' => ['nullable', 'string', 'max:191'],
            'include_pii' => ['nullable', 'boolean'],
        ]);

        $includePii = (bool) ($validated['include_pii'] ?? false);
        if ($includePii) {
            abort_unless(Gate::allows(PermissionKey::ReportsBiPii->value), 403);
        }

        /** @var list<string> $fields */
        $fields = array_values($validated['fields']);
        $result = $this->exports->export(
            companyId: (int) $this->companyContext->requireCompany()->getKey(),
            datasetKey: $datasetKey,
            format: (string) $validated['format'],
            requestedFields: $fields,
            watermark: isset($validated['watermark']) ? (string) $validated['watermark'] : null,
            includePii: $includePii,
            branchId: $this->branchContext->id(),
        );

        $extension = $result['format'] === 'json' ? 'json' : 'csv';

        return response($result['content'], 200, [
            'Content-Type' => $result['format'] === 'json' ? 'application/json; charset=UTF-8' : 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="mars-bi-'.$datasetKey.'.'.$extension.'"',
            'X-BI-Run-ID' => (string) $result['run_id'],
            'X-BI-Schema-Version' => (string) $result['schema_version'],
            'X-BI-Artifact-SHA256' => $result['sha256'],
            'X-BI-Watermark' => $result['watermark'] ?? '',
        ]);
    }

    private function guardFeature(): void
    {
        abort_unless($this->features->enabled(FeatureKey::BiExports), 404);
    }
}
