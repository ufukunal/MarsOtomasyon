<?php

namespace App\Support\Concurrency;

use App\Exceptions\StaleRecordException;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Stringable;

/**
 * @mixin Model
 */
trait HasOptimisticLock
{
    /**
     * Atomik compare-and-swap yazar; normal Eloquent lifecycle'ının
     * türetilmiş alan ve audit davranışlarını korur.
     *
     * @param array<string, mixed> $attributes
     */
    public function updateWithVersion(array $attributes, int $expectedVersion): static
    {
        unset($attributes['version']);

        /** @var static $current */
        $current = static::query()->findOrFail($this->getKey());

        if ((int) $current->version !== $expectedVersion) {
            throw $this->staleException($current, $attributes, $expectedVersion);
        }

        $current->fill($attributes);
        $current->setAttribute('version', $expectedVersion + 1);

        if ($current->fireModelEvent('saving') === false
            || $current->fireModelEvent('updating') === false) {
            throw new RuntimeException(sprintf(
                '%s#%s güncellemesi model lifecycle tarafından reddedildi.',
                static::class,
                (string) $this->getKey(),
            ));
        }

        if ($current->usesTimestamps()) {
            $current->updateTimestamps();
        }

        // Event listener'ları türetilmiş alanları değiştirebilir; SQL payload
        // eventlerden sonra hesaplanır.
        $dirty = $current->getDirty();

        $updated = static::query()
            ->whereKey($this->getKey())
            ->where('version', $expectedVersion)
            ->update($dirty);

        if ($updated !== 1) {
            throw new StaleRecordException(
                sprintf('%s#%s eşzamanlı olarak değiştirildi.', static::class, (string) $this->getKey()),
            );
        }

        // Eloquent performUpdate/finishSave sırasındaki post-event durumunu
        // compare-and-swap başarıyla tamamlandıktan sonra üret.
        $current->syncChanges();
        $current->fireModelEvent('updated', false);
        $current->fireModelEvent('saved', false);
        $current->syncOriginal();

        return $current;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function staleException(Model $current, array $attributes, int $expectedVersion): StaleRecordException
    {
        $changed = [];

        foreach ($attributes as $field => $newValue) {
            if ($this->comparableValue($current->getAttribute($field)) !== $this->comparableValue($newValue)) {
                $changed[] = $field;
            }
        }

        $suffix = $changed === []
            ? ''
            : ' Değişmiş alanlar: '.implode(', ', $changed).'.';

        return new StaleRecordException(
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

    private function comparableValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';
    }
}
