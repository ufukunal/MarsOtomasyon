<?php

namespace App\Modules\Reports\Bi;

use App\Foundation\Features\FeatureKey;
use App\Foundation\Features\FeatureRegistry;
use App\Modules\Core\Branch\ActiveBranchContext;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\PermissionKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
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

    public function index(): View
    {
        $this->guardFeature();
        $companyId = (int) $this->companyContext->requireCompany()->getKey();

        return view('reports.bi.index', [
            'datasets' => $this->datasets->catalog(),
            'schedules' => DB::table('bi_export_schedules')->where('company_id', $companyId)->orderBy('schedule_key')->get(),
            'runs' => DB::table('bi_export_runs')->where('company_id', $companyId)->orderByDesc('id')->limit(50)->get(),
            'canPii' => Gate::allows(PermissionKey::ReportsBiPii->value),
        ]);
    }

    public function catalog(): JsonResponse
    {
        $this->guardFeature();

        return response()->json(['datasets' => $this->datasets->catalog()]);
    }

    public function storeSchedule(Request $request): RedirectResponse
    {
        $this->guardFeature();
        abort_unless(Gate::allows(PermissionKey::ReportsBiExport->value), 403);

        $validated = $request->validate([
            'schedule_key' => ['required', 'string', 'max:128', 'regex:/^[A-Za-z0-9_.-]+$/'],
            'dataset_key' => ['required', 'string', 'max:128'],
            'format' => ['required', 'in:csv,json'],
            'fields' => ['required', 'array', 'min:1'],
            'fields.*' => ['required', 'string', 'max:128'],
            'include_pii' => ['nullable', 'boolean'],
            'interval_minutes' => ['required', 'integer', 'min:5', 'max:525600'],
            'next_run_at' => ['required', 'date'],
        ]);

        $dataset = $this->datasets->get((string) $validated['dataset_key']);
        $definition = $dataset->fields();
        /** @var list<string> $fields */
        $fields = array_values($validated['fields']);
        foreach ($fields as $field) {
            abort_unless(isset($definition[$field]), 422, 'BI dataset field is not allow-listed.');
        }

        $includePii = (bool) ($validated['include_pii'] ?? false);
        if ($includePii) {
            abort_unless(Gate::allows(PermissionKey::ReportsBiPii->value), 403);
        }

        $companyId = (int) $this->companyContext->requireCompany()->getKey();
        DB::table('bi_export_schedules')->updateOrInsert(
            ['company_id' => $companyId, 'schedule_key' => (string) $validated['schedule_key']],
            [
                'branch_id' => $this->branchContext->id(),
                'created_by_user_id' => Auth::id(),
                'dataset_key' => $dataset->key(),
                'schema_version' => $dataset->schemaVersion(),
                'format' => (string) $validated['format'],
                'fields' => json_encode($fields, JSON_THROW_ON_ERROR),
                'include_pii' => $includePii,
                'interval_minutes' => (int) $validated['interval_minutes'],
                'is_enabled' => true,
                'next_run_at' => (string) $validated['next_run_at'],
                'last_error' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        return redirect()->route('reports.bi.index')->with('status', 'BI export planı kaydedildi.');
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
