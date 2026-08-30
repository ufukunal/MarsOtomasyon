<?php

namespace App\Modules\Core\Shell;

use App\Foundation\Features\FeatureKey;
use App\Foundation\Features\FeatureRegistry;
use App\Modules\Core\Enums\PermissionKey;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

final readonly class AppNavigation
{
    public function __construct(private FeatureRegistry $features) {}

    /** @return list<array{label:string,route:string}> */
    public function items(): array
    {
        $productStockRoute = Gate::allows(PermissionKey::ProductView->value) ? 'inventory.index' : 'inventory.stock.index';
        /** @var list<array{label:string,route:string,feature:FeatureKey,permissions:list<PermissionKey>}> $candidates */
        $candidates = [
            ['label' => 'Ana Sayfa', 'route' => 'workspace', 'feature' => FeatureKey::Foundation, 'permissions' => []],
            ['label' => 'Cariler', 'route' => 'customers.index', 'feature' => FeatureKey::Customers, 'permissions' => [PermissionKey::AccountView]],
            ['label' => 'Ürün/Stok', 'route' => $productStockRoute, 'feature' => FeatureKey::ProductStock, 'permissions' => [PermissionKey::ProductView, PermissionKey::InventoryView]],
            ['label' => 'Satış', 'route' => 'sales.index', 'feature' => FeatureKey::Sales, 'permissions' => [PermissionKey::SalesOrderView, PermissionKey::DispatchView, PermissionKey::SalesInvoiceView]],
            ['label' => 'Alış', 'route' => 'purchasing.index', 'feature' => FeatureKey::Purchasing, 'permissions' => [PermissionKey::PurchaseOrderView]],
            ['label' => 'Üretim', 'route' => 'production.index', 'feature' => FeatureKey::Production, 'permissions' => [PermissionKey::ProductionView]],
            ['label' => 'Kasa/Banka', 'route' => 'treasury.index', 'feature' => FeatureKey::Treasury, 'permissions' => [PermissionKey::TreasuryView]],
            ['label' => 'Çek/Senet', 'route' => 'instruments.index', 'feature' => FeatureKey::Instruments, 'permissions' => [PermissionKey::InstrumentView]],
            ['label' => 'İadeler', 'route' => 'returns.index', 'feature' => FeatureKey::Returns, 'permissions' => [PermissionKey::SalesReturnView]],
            ['label' => 'İthalat', 'route' => 'import.index', 'feature' => FeatureKey::Import, 'permissions' => []],
            ['label' => 'E-Ticaret/B2B', 'route' => 'commerce.index', 'feature' => FeatureKey::Commerce, 'permissions' => [PermissionKey::IntegrationView]],
            ['label' => 'İletişim', 'route' => 'communications.index', 'feature' => FeatureKey::Communications, 'permissions' => [PermissionKey::NotificationView]],
            ['label' => 'Operasyon', 'route' => 'operations.index', 'feature' => FeatureKey::Operations, 'permissions' => [PermissionKey::OperationsView]],
            ['label' => 'Raporlar', 'route' => 'reports.index', 'feature' => FeatureKey::Reports, 'permissions' => []],
            ['label' => 'Ayarlar', 'route' => 'settings.index', 'feature' => FeatureKey::Foundation, 'permissions' => []],
        ];
        $items = [];
        foreach ($candidates as $candidate) {
            if (! $this->features->enabled($candidate['feature']) || ! Route::has($candidate['route'])) {
                continue;
            }
            if ($candidate['permissions'] !== []) {
                $allowed = false;
                foreach ($candidate['permissions'] as $permission) {
                    if (Gate::allows($permission->value)) {
                        $allowed = true;
                        break;
                    }
                }
                if (! $allowed) {
                    continue;
                }
            }
            $items[] = ['label' => $candidate['label'], 'route' => $candidate['route']];
        }

        return $items;
    }
}
