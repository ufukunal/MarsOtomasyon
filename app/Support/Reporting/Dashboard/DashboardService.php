<?php

namespace App\Support\Reporting\Dashboard;

use App\Actions\Reporting\RunReport;
use App\Models\User;
use App\Support\Formatting\TrFormatter;
use App\Support\Reporting\ReportCatalog;
use App\Support\Reporting\ReportRequest;
use Carbon\CarbonImmutable;

final class DashboardService
{
    public function __construct(
        private readonly ReportCatalog $catalog,
        private readonly RunReport $runReport,
    ) {}

    /** @return list<DashboardWidget> */
    public function widgets(?User $actor = null): array
    {
        $actor ??= auth()->user();

        if (! $actor) {
            return [];
        }

        $availableReports = array_fill_keys(
            array_map(
                fn ($item): string => $item->key,
                $this->catalog->forActor($actor),
            ),
            true,
        );

        $widgets = [];

        foreach ($this->definitions($actor) as $definition) {
            if (! isset($availableReports[$definition->reportKey])) {
                continue;
            }

            $result = $this->runReport->handle(
                $definition->reportKey,
                new ReportRequest(
                    filters: $definition->filters,
                    columns: $definition->columns,
                    limit: 1,
                    offset: 0,
                ),
                $actor,
            );

            $rawValue = $definition->totalKey === null
                ? $result->totalRows
                : ($result->totals[$definition->totalKey] ?? null);

            if ($rawValue === null) {
                continue;
            }

            $widgets[] = new DashboardWidget(
                key: $definition->key,
                title: $definition->title,
                description: $definition->description,
                value: $this->formatValue($rawValue, $definition->valueType),
                url: $actor->can('reports.view')
                    ? route('reports.center', [
                        'report' => $definition->reportKey,
                        'filters' => $definition->filters,
                    ])
                    : null,
            );
        }

        return $widgets;
    }

    /** @return list<DashboardWidgetDefinition> */
    private function definitions(User $actor): array
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $monthFrom = $today->startOfMonth()->format('Y-m-d');
        $todayDate = $today->format('Y-m-d');
        $nextThirtyDays = $today->addDays(30)->format('Y-m-d');

        $definitions = [
            new DashboardWidgetDefinition(
                key: 'sales_month_count',
                title: 'Bu Ay Satış Faturası',
                description: $monthFrom.' – '.$todayDate,
                reportKey: 'sales.invoices',
                columns: ['id'],
                filters: ['date_from' => $monthFrom, 'date_to' => $todayDate],
            ),
            new DashboardWidgetDefinition(
                key: 'sales_month_try',
                title: 'Bu Ay Satış (TRY)',
                description: 'TRY satış faturası toplamı',
                reportKey: 'sales.invoices',
                columns: ['id'],
                filters: [
                    'date_from' => $monthFrom,
                    'date_to' => $todayDate,
                    'currency' => 'TRY',
                ],
                totalKey: 'grand_total',
                valueType: 'money',
            ),
            new DashboardWidgetDefinition(
                key: 'purchase_month_try',
                title: 'Bu Ay Alış (TRY)',
                description: 'TRY tedarikçi faturası toplamı',
                reportKey: 'purchases.supplier_invoices',
                columns: ['id'],
                filters: [
                    'date_from' => $monthFrom,
                    'date_to' => $todayDate,
                    'currency' => 'TRY',
                ],
                totalKey: 'grand_total',
                valueType: 'money',
            ),
            new DashboardWidgetDefinition(
                key: 'contact_balance_try',
                title: 'Cari Net Bakiye (TRY)',
                description: 'Aktif dönem cari ledger net etkisi',
                reportKey: 'contacts.transactions',
                columns: ['id'],
                filters: ['currency' => 'TRY'],
                totalKey: 'balance',
                valueType: 'money',
            ),
            new DashboardWidgetDefinition(
                key: 'securities_due_try',
                title: '30 Günlük Çek/Senet (TRY)',
                description: $todayDate.' – '.$nextThirtyDays,
                reportKey: 'securities.portfolio',
                columns: ['id'],
                filters: [
                    'date_from' => $todayDate,
                    'date_to' => $nextThirtyDays,
                    'currency' => 'TRY',
                ],
                totalKey: 'amount',
                valueType: 'money',
            ),
            new DashboardWidgetDefinition(
                key: 'import_file_count',
                title: 'İthalat Dosyaları',
                description: 'Aktif dönem kayıt sayısı',
                reportKey: 'imports.files',
                columns: ['id'],
            ),
            new DashboardWidgetDefinition(
                key: 'production_order_count',
                title: 'Üretim / Fason Emirleri',
                description: 'Aktif dönem kayıt sayısı',
                reportKey: 'production.orders',
                columns: ['id'],
            ),
            new DashboardWidgetDefinition(
                key: 'channel_failed_sync',
                title: 'Hatalı Kanal Sync',
                description: 'Çözülmemiş durumdan bağımsız failed event sayısı',
                reportKey: 'channels.sync_status',
                columns: ['id'],
                filters: ['status' => 'failed'],
                totalKey: 'failed',
            ),
        ];

        $definitions[] = $actor->can('cost.view')
            ? new DashboardWidgetDefinition(
                key: 'stock_value',
                title: 'Stok Değeri',
                description: 'Aktif fiziksel stok · TRY hareketli ortalama',
                reportKey: 'stock.balances',
                columns: ['product_id'],
                filters: ['active_only' => true],
                totalKey: 'stock_value',
                valueType: 'money',
            )
            : new DashboardWidgetDefinition(
                key: 'stock_scope',
                title: 'Aktif Stok Kapsamı',
                description: 'Ürün/lokasyon görünüm satırı',
                reportKey: 'stock.balances',
                columns: ['product_id'],
                filters: ['active_only' => true],
            );

        return $definitions;
    }

    private function formatValue(mixed $value, string $type): string
    {
        return match ($type) {
            'money' => TrFormatter::money((string) $value).' ₺',
            'quantity' => TrFormatter::quantity((string) $value),
            default => number_format((int) $value, 0, ',', '.'),
        };
    }
}
