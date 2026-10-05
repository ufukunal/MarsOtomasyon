<?php

namespace App\Actions\Reporting;

use App\Models\ReportFilterPreset;
use App\Models\User;
use App\Support\Period\PeriodContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

final class DeleteReportPreset
{
    public function handle(int $presetId, ?User $actor = null): void
    {
        PeriodContext::ensure();
        $actor ??= auth()->user();

        if (! $actor || ! $actor->is_active) {
            throw new AuthorizationException('Preset silmek için aktif kullanıcı gereklidir.');
        }

        $preset = ReportFilterPreset::query()
            ->whereKey($presetId)
            ->where('company_id', PeriodContext::companyId())
            ->firstOrFail();

        if ($preset->is_shared) {
            Gate::forUser($actor)->authorize('reports.presets.share');
        } elseif ($preset->user_id !== $actor->id) {
            throw new AuthorizationException('Başka kullanıcının preset kaydı silinemez.');
        }

        $preset->delete();
    }
}
