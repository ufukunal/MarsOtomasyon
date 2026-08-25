<?php

namespace Tests\Unit;

use App\Foundation\Features\FeatureKey;
use App\Foundation\Features\FeatureRegistry;
use Tests\TestCase;

final class FoundationFeatureRegistryTest extends TestCase
{
    public function test_implemented_foundation_customers_product_stock_and_sales_features_are_enabled(): void
    {
        $registry = $this->app->make(FeatureRegistry::class);

        self::assertTrue($registry->enabled(FeatureKey::Foundation));
        self::assertTrue($registry->enabled(FeatureKey::Customers));
        self::assertTrue($registry->enabled(FeatureKey::ProductStock));
        self::assertTrue($registry->enabled(FeatureKey::Sales));
    }

    public function test_future_business_features_remain_disabled_until_implemented(): void
    {
        $registry = $this->app->make(FeatureRegistry::class);

        foreach (FeatureKey::cases() as $feature) {
            if (in_array($feature, [FeatureKey::Foundation, FeatureKey::Customers, FeatureKey::ProductStock, FeatureKey::Sales], true)) {
                continue;
            }

            self::assertFalse($registry->enabled($feature), $feature->value.' must remain disabled until its milestone is implemented.');
        }
    }
}
