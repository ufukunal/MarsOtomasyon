<?php

namespace App\Models\Concerns;

use App\Exceptions\StaleModelVersionException;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 */
trait HasOptimisticLock
{
    public function updateWithVersion(array $attributes, int $expectedVersion): static
    {
        unset($attributes['version']);

        $updated = static::query()
            ->whereKey($this->getKey())
            ->where('version', $expectedVersion)
            ->update([
                ...$attributes,
                'version' => $expectedVersion + 1,
            ]);

        if ($updated !== 1) {
            throw new StaleModelVersionException(
                sprintf('%s#%s stale version: expected %d.', static::class, (string) $this->getKey(), $expectedVersion),
            );
        }

        return $this->refresh();
    }
}
