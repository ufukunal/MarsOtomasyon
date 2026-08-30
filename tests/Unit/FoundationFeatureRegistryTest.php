<?php

namespace Tests\Unit;

use App\Foundation\Features\FeatureKey;
use App\Foundation\Features\FeatureRegistry;
use Tests\TestCase;

final class FoundationFeatureRegistryTest extends TestCase
{
    public function test_delivered_business_and_operations_features_are_enabled(): void
    {
        $registry = $this->app->make(FeatureRegistry::class);

        foreach (FeatureKey::cases() as $feature) {
            self::assertTrue($registry->enabled($feature), $feature->value.' must be enabled after its milestone delivery.');
        }
    }
}
