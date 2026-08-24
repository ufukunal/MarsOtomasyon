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

    public function test_only_real_foundation_feature_is_declared_at_m0(): void
    {
        self::assertSame(['foundation' => true], config('mars.features'));
    }
}
