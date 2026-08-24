<?php

namespace Tests\Unit;

use App\Foundation\Features\FeatureKey;
use App\Foundation\Features\FeatureRegistry;
use Tests\TestCase;

final class FoundationFeatureRegistryTest extends TestCase
{
    public function test_foundation_feature_is_explicitly_enabled(): void
    {
        self::assertTrue($this->app->make(FeatureRegistry::class)->enabled(FeatureKey::Foundation));
    }

    public function test_future_business_features_are_declared_but_disabled_until_implemented(): void
    {
        $registry = $this->app->make(FeatureRegistry::class);

        foreach (FeatureKey::cases() as $feature) {
            if ($feature === FeatureKey::Foundation) {
                continue;
            }

            self::assertFalse($registry->enabled($feature), $feature->value.' must remain disabled until its milestone is implemented.');
        }
    }
}
