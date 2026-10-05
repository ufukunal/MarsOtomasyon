<?php

namespace App\Support\Reporting;

use App\Models\ReportFilterPreset;
use App\Models\User;
use App\Support\Period\PeriodContext;
use Illuminate\Database\Eloquent\Collection;

final class ReportPresetRepository
{
    /** @return Collection<int,ReportFilterPreset> */
    public function forReport(string $reportKey, User $actor): Collection
    {
        PeriodContext::ensure();

        return ReportFilterPreset::query()
            ->where('company_id', PeriodContext::companyId())
            ->where('report_key', $reportKey)
            ->where(function ($query) use ($actor): void {
                $query->where(function ($personal) use ($actor): void {
                    $personal->where('is_shared', false)
                        ->where('user_id', $actor->id);
                })->orWhere(function ($shared): void {
                    $shared->where('is_shared', true)
                        ->whereNull('user_id');
                });
            })
            ->orderByDesc('is_shared')
            ->orderBy('name')
            ->get();
    }
}
