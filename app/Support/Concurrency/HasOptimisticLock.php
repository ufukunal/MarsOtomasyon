<?php

namespace App\Support\Concurrency;

use App\Exceptions\StaleRecordException;
use Illuminate\Database\Eloquent\Model;

/**
 * @mixin Model
 */
trait HasOptimisticLock
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function updateWithVersion(array $attributes, int $expectedVersion): static
    {
        unset($attributes['version']);

        $current = static::query()->findOrFail($this->getKey());

        if ((int) $current->version !== $expectedVersion) {
            $changed = [];

            foreach ($attributes as $field => $newValue) {
                $currentValue = $current->getAttribute($field);

                if ((string) $currentValue !== (string) $newValue) {
                    $changed[] = $field;
                }
            }

            $suffix = $changed === []
                ? ''
                : ' Değişmiş alanlar: '.implode(', ', $changed).'.';

            throw new StaleRecordException(
                sprintf(
                    '%s#%s güncel değil. Beklenen sürüm %d, mevcut sürüm %d.%s',
                    static::class,
                    (string) $this->getKey(),
                    $expectedVersion,
                    (int) $current->version,
                    $suffix,
                ),
            );
        }

        $updated = static::query()
            ->whereKey($this->getKey())
            ->where('version', $expectedVersion)
            ->update([
                ...$attributes,
                'version' => $expectedVersion + 1,
            ]);

        if ($updated !== 1) {
            throw new StaleRecordException(
                sprintf('%s#%s eşzamanlı olarak değiştirildi.', static::class, (string) $this->getKey()),
            );
        }

        return $this->refresh();
    }
}
