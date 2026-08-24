<?php

namespace App\Modules\Core\Shell;

use App\Foundation\Features\FeatureKey;
use App\Foundation\Features\FeatureRegistry;
use Illuminate\Support\Facades\Route;

final readonly class AppNavigation
{
    public function __construct(private FeatureRegistry $features) {}

    /** @return list<array{label:string,route:string}> */
    public function items(): array
    {
        /** @var list<array{label:string,route:string,feature:FeatureKey}> $candidates */
        $candidates = [
            ['label' => 'Ana Sayfa', 'route' => 'workspace', 'feature' => FeatureKey::Foundation],
            ['label' => 'Cariler', 'route' => 'customers.index', 'feature' => FeatureKey::Customers],
            ['label' => 'Ürün/Stok', 'route' => 'inventory.index', 'feature' => FeatureKey::ProductStock],
            ['label' => 'Satış', 'route' => 'sales.index', 'feature' => FeatureKey::Sales],
            ['label' => 'Alış', 'route' => 'purchasing.index', 'feature' => FeatureKey::Purchasing],
            ['label' => 'Üretim', 'route' => 'production.index', 'feature' => FeatureKey::Production],
            ['label' => 'Kasa/Banka', 'route' => 'treasury.index', 'feature' => FeatureKey::Treasury],
            ['label' => 'Çek/Senet', 'route' => 'instruments.index', 'feature' => FeatureKey::Instruments],
            ['label' => 'İadeler', 'route' => 'returns.index', 'feature' => FeatureKey::Returns],
            ['label' => 'İthalat', 'route' => 'import.index', 'feature' => FeatureKey::Import],
            ['label' => 'E-Ticaret/B2B', 'route' => 'commerce.index', 'feature' => FeatureKey::Commerce],
            ['label' => 'İletişim', 'route' => 'communications.index', 'feature' => FeatureKey::Communications],
            ['label' => 'Raporlar', 'route' => 'reports.index', 'feature' => FeatureKey::Reports],
            ['label' => 'Ayarlar', 'route' => 'settings.index', 'feature' => FeatureKey::Foundation],
        ];

        $items = [];
        foreach ($candidates as $candidate) {
            if (! $this->features->enabled($candidate['feature']) || ! Route::has($candidate['route'])) {
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
