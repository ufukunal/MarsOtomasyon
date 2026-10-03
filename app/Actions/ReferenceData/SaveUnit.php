<?php

namespace App\Actions\ReferenceData;

use App\Support\Auth\MutationAuthorizer;
use App\Models\Period\Unit;
use Illuminate\Validation\ValidationException;
use LogicException;

final class SaveUnit
{
    public function handle(array $data, ?Unit $unit = null, ?int $expectedVersion = null): Unit
    {
        MutationAuthorizer::authorize($unit ? 'units.update' : 'units.create');
        $attributes = [
            'code' => strtoupper(trim((string) $data['code'])),
            'name' => trim((string) $data['name']),
            'is_base' => (bool) ($data['is_base'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        if ($unit) {
            if ($unit->code !== $attributes['code']) {
                throw new LogicException('Birim kodu kayıt sonrası değiştirilemez.');
            }

            if ($unit->is_base && ! $attributes['is_base'] && ! Unit::query()
                ->whereKeyNot($unit->id)
                ->where('is_base', true)
                ->where('is_active', true)
                ->exists()) {
                throw ValidationException::withMessages([
                    'is_base' => 'En az bir aktif temel birim bulunmalıdır.',
                ]);
            }

            return $unit->updateWithVersion(
                $attributes,
                $expectedVersion ?? (int) $unit->version,
            );
        }

        return Unit::query()->create($attributes);
    }
}
