<?php

namespace Tests\Unit;

use App\Foundation\Features\FeatureKey;
use App\Foundation\Features\FeatureRegistry;
use Tests\TestCase;

final class FoundationFeatureRegistryTest extends TestCase
{
    public function test_implemented_foundation_business_and_m11_operations_features_are_enabled(): void
    {
        $registry = $this->app->make(FeatureRegistry::class);
        foreach ([
            FeatureKey::Foundation,
            FeatureKey::Customers,
            FeatureKey::ProductStock,
            FeatureKey::Sales,
            FeatureKey::Commerce,
            FeatureKey::Communications,
            FeatureKey::Automation,
            FeatureKey::Operations,
        ] as $feature) {
            self::assertTrue($registry->enabled($feature), $feature->value.' must be enabled.');
        }
    }

    public function test_unimplemented_business_features_remain_disabled(): void
    {
        $registry = $this->app->make(FeatureRegistry::class);
        foreach ([
            FeatureKey::Purchasing,
            FeatureKey::Production,
            FeatureKey::Treasury,
            FeatureKey::Instruments,
            FeatureKey::Returns,
            FeatureKey::Import,
            FeatureKey::Reports,
        ] as $feature) {
            self::assertFalse($registry->enabled($feature), $feature->value.' must remain disabled until its milestone is enabled.');
        }
    }
}
