<?php

namespace Tests\Unit;

use App\Foundation\Features\FeatureKey;
use App\Foundation\Features\FeatureRegistry;
use Tests\TestCase;

final class FoundationFeatureRegistryTest extends TestCase
{
    public function test_implemented_foundation_business_and_operations_features_are_enabled(): void
    {
        $registry = $this->app->make(FeatureRegistry::class);
        foreach ([
            FeatureKey::Foundation,
            FeatureKey::Customers,
            FeatureKey::ProductStock,
            FeatureKey::Sales,
            FeatureKey::Purchasing,
            FeatureKey::Production,
            FeatureKey::Treasury,
            FeatureKey::Instruments,
            FeatureKey::Returns,
            FeatureKey::Import,
            FeatureKey::Commerce,
            FeatureKey::Communications,
            FeatureKey::Automation,
            FeatureKey::Operations,
            FeatureKey::Reports,
        ] as $feature) {
            self::assertTrue($registry->enabled($feature), $feature->value.' must be enabled.');
        }
    }
}
