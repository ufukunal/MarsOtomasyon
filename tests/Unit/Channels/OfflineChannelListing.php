<?php

use App\Models\Period\ChannelProductListing;

/**
 * Unit-only fixture for pure marketplace payload formatting.
 * Any attempted lazy relation fetch fails instead of touching a period DB.
 */
final class MarsOfflineChannelListing extends ChannelProductListing
{
    public function loadMissing($relations)
    {
        $names = is_string($relations) ? [$relations] : (array) $relations;

        foreach ($names as $relation) {
            $root = explode('.', (string) $relation, 2)[0];
            if (! $this->relationLoaded($root)) {
                throw new LogicException("Unexpected lazy relation fetch in offline channel test: {$root}");
            }
        }

        return $this;
    }
}
