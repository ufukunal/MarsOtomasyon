<?php

namespace App\Foundation\Features;

final readonly class FeatureRegistry
{
    /**
     * Feature availability is separate from authorization.
     */
    public function enabled(FeatureKey $feature): bool
    {
        return (bool) config("mars.features.{$feature->value}", false);
    }
}
