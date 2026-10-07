<?php

namespace App\Http\Controllers;

use App\Models\ReportExportJob;
use App\Support\Auth\PeriodPermissionContext;
use App\Support\Period\PeriodContext;
use App\Support\Reporting\ReportRegistry;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportExportDownloadController
{
    public function __invoke(
        ReportExportJob $export,
        ReportRegistry $registry,
    ): StreamedResponse {
        $actor = auth()->user();

        if (! $actor || ! $actor->is_active || $export->user_id !== $actor->id) {
            throw new AuthorizationException('Bu export dosyasına erişiminiz yok.');
        }

        if (PeriodContext::companyId() !== $export->company_id) {
            throw new AuthorizationException('Export farklı şirket bağlamına aittir.');
        }

        $periodId = (int) (($export->periods ?? [])[0] ?? 0);
        $periodAccess = $periodId > 0
            ? DB::connection('master')
                ->table('period_user_access')
                ->where('period_id', $periodId)
                ->where('user_id', $actor->id)
                ->where('is_active', true)
                ->first(['permission_overrides'])
            : null;

        if (! $periodAccess) {
            throw new AuthorizationException('Export kaynak dönemine erişiminiz yok.');
        }

        $currentPeriodId = (int) (PeriodContext::periodId() ?? 0);
        $currentAccess = $currentPeriodId > 0
            ? DB::connection('master')
                ->table('period_user_access')
                ->where('period_id', $currentPeriodId)
                ->where('user_id', $actor->id)
                ->where('is_active', true)
                ->first(['permission_overrides'])
            : null;

        $sourceOverrides = $this->decodeOverrides($periodAccess->permission_overrides);
        $currentOverrides = $this->decodeOverrides($currentAccess?->permission_overrides);

        PeriodPermissionContext::clear();
        PeriodPermissionContext::use($sourceOverrides);

        try {
            $definition = $registry->query($export->report_key)->definition();
            Gate::forUser($actor)->authorize($definition->permission);

            if ((bool) ($export->permission_scope['cost_view_required'] ?? false)) {
                Gate::forUser($actor)->authorize('cost.view');
            }
        } finally {
            PeriodPermissionContext::clear();

            if ($currentAccess) {
                PeriodPermissionContext::use($currentOverrides);
            }
        }

        if ($export->status !== 'done' || ! $export->storage_disk || ! $export->storage_path) {
            abort(404);
        }

        $disk = Storage::disk($export->storage_disk);

        if (! $disk->exists($export->storage_path)) {
            abort(404);
        }

        $filename = str_replace('.', '-', $export->report_key)
            .'-'.$export->id.'.'.$export->format;

        return $disk->download($export->storage_path, $filename);
    }

    /** @return array<string,mixed> */
    private function decodeOverrides(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true) ?: [];
        }

        return is_array($value) ? $value : [];
    }
}
