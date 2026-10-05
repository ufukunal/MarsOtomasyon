<?php

namespace App\Support\Reporting;

use App\Models\User;
use App\Support\Period\PeriodContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

final class ReportCatalog
{
    public function __construct(private readonly ReportRegistry $registry) {}

    /** @return list<ReportCatalogItem> */
    public function forActor(?User $actor = null): array
    {
        PeriodContext::ensure();
        $actor ??= auth()->user();

        if (! $actor) {
            throw new AuthorizationException('Rapor kataloğu için kullanıcı bağlamı gereklidir.');
        }

        $gate = Gate::forUser($actor);
        $canViewCost = $gate->allows('cost.view');
        $items = [];

        foreach ($this->registry->definitions() as $definition) {
            if (! $gate->allows($definition->permission)) {
                continue;
            }

            $columns = array_values(array_filter(
                $definition->columns,
                fn (ReportColumnDefinition $column): bool => $canViewCost || ! $column->costSensitive,
            ));
            $columnKeys = array_fill_keys(
                array_map(fn (ReportColumnDefinition $column): string => $column->key, $columns),
                true,
            );
            $totals = array_values(array_filter(
                $definition->totals,
                fn (ReportTotalDefinition $total): bool => $canViewCost || ! $total->costSensitive,
            ));
            $sort = array_values(array_filter(
                $definition->defaultSort,
                fn (ReportSort $item): bool => isset($columnKeys[$item->key]),
            ));
            $drillDowns = array_values(array_filter(
                $definition->drillDowns,
                fn (ReportDrillDownDefinition $item): bool => $gate->allows($item->permission),
            ));

            $items[] = new ReportCatalogItem(
                key: $definition->key,
                title: $definition->title,
                category: $definition->category,
                filters: $definition->filters,
                columns: $columns,
                defaultSort: $sort,
                totals: $totals,
                drillDowns: $drillDowns,
                exporters: $definition->exporters,
                version: $definition->version,
            );
        }

        usort(
            $items,
            fn (ReportCatalogItem $a, ReportCatalogItem $b): int => [$a->category, $a->title, $a->key]
                <=> [$b->category, $b->title, $b->key],
        );

        return $items;
    }
}
