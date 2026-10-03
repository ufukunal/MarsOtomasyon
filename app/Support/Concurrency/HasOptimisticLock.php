<?php

namespace App\Support\Concurrency;

use App\Exceptions\StaleRecordException;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Stringable;

/**
 * @mixin Model
 */
trait HasOptimisticLock
{
    /**
     * Satırı FOR UPDATE ile kilitleyip güncel version değerini doğrular.
     * Ardından normal Eloquent save lifecycle'ını çalıştırır; böylece
     * saving/updating/updated/saved listener'ları ve audit/search türevleri
     * doğal biçimde korunur.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateWithVersion(array $attributes, int $expectedVersion): static
    {
        unset($attributes['version']);

        return DB::connection($this->getConnectionName())->transaction(
            function () use ($attributes, $expectedVersion): static {
                /** @var static $current */
                $current = static::query()
                    ->lockForUpdate()
                    ->findOrFail($this->getKey());

                if ((int) $current->getAttribute('version') !== $expectedVersion) {
                    throw $this->staleException($current, $attributes, $expectedVersion);
                }

                $current->fill($attributes);
                $current->setAttribute('version', $expectedVersion + 1);
                $current->save();

                return $current->refresh();
            },
            attempts: 3,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
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
                (int) $current->getAttribute('version'),
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
