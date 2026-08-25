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
        /** @var list<array{label:string,route:string,feature:FeatureKey,permission:?PermissionKey}> $candidates */
        $candidates = [
            ['label' => 'Ana Sayfa', 'route' => 'workspace', 'feature' => FeatureKey::Foundation, 'permission' => null],
            ['label' => 'Cariler', 'route' => 'customers.index', 'feature' => FeatureKey::Customers, 'permission' => PermissionKey::AccountView],
            ['label' => 'Ürün/Stok', 'route' => 'inventory.index', 'feature' => FeatureKey::ProductStock, 'permission' => PermissionKey::ProductView],
            ['label' => 'Satış', 'route' => 'sales.index', 'feature' => FeatureKey::Sales, 'permission' => null],
            ['label' => 'Alış', 'route' => 'purchasing.index', 'feature' => FeatureKey::Purchasing, 'permission' => null],
            ['label' => 'Üretim', 'route' => 'production.index', 'feature' => FeatureKey::Production, 'permission' => null],
            ['label' => 'Kasa/Banka', 'route' => 'treasury.index', 'feature' => FeatureKey::Treasury, 'permission' => null],
            ['label' => 'Çek/Senet', 'route' => 'instruments.index', 'feature' => FeatureKey::Instruments, 'permission' => null],
            ['label' => 'İadeler', 'route' => 'returns.index', 'feature' => FeatureKey::Returns, 'permission' => null],
            ['label' => 'İthalat', 'route' => 'import.index', 'feature' => FeatureKey::Import, 'permission' => null],
            ['label' => 'E-Ticaret/B2B', 'route' => 'commerce.index', 'feature' => FeatureKey::Commerce, 'permission' => null],
            ['label' => 'İletişim', 'route' => 'communications.index', 'feature' => FeatureKey::Communications, 'permission' => null],
            ['label' => 'Raporlar', 'route' => 'reports.index', 'feature' => FeatureKey::Reports, 'permission' => null],
            ['label' => 'Ayarlar', 'route' => 'settings.index', 'feature' => FeatureKey::Foundation, 'permission' => null],
        ];

        $items = [];
        foreach ($candidates as $candidate) {
            if (! $this->features->enabled($candidate['feature']) || ! Route::has($candidate['route'])) {
                continue;
            }

            if ($candidate['permission'] !== null && Gate::denies($candidate['permission']->value)) {
                continue;
            }

            $items[] = [
                'label' => $candidate['label'],
                'route' => $candidate['route'],
            ];
        }

        return $items;
    }
}
