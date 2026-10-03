<?php

namespace App\Actions\Locations;

use App\Enums\LocationKind;
use App\Models\Period\Location;
use App\Support\Period\PeriodContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveLocation
{
    public function handle(array $data, ?Location $location = null, ?int $expectedVersion = null): Location
    {
        PeriodContext::ensureWritable();

        $kind = LocationKind::from((string) $data['kind']);
        $plate = trim((string) ($data['plate'] ?? ''));

        if ($kind === LocationKind::Vehicle && $plate === '') {
            throw ValidationException::withMessages([
                'plate' => 'Araç lokasyonunda plaka zorunludur.',
            ]);
        }

        if ($kind !== LocationKind::Vehicle) {
            $plate = '';
        }

        return DB::connection('period')->transaction(function () use ($data, $location, $expectedVersion, $kind, $plate): Location {
            if ((bool) ($data['is_default'] ?? false)) {
                Location::query()
                    ->when($location, fn ($query) => $query->whereKeyNot($location->getKey()))
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $attributes = [
                'code' => strtoupper(trim((string) $data['code'])),
                'name' => trim((string) $data['name']),
                'kind' => $kind->value,
                'plate' => $plate !== '' ? strtoupper($plate) : null,
                'address' => trim((string) ($data['address'] ?? '')) ?: null,
                'is_default' => (bool) ($data['is_default'] ?? false),
                'is_active' => (bool) ($data['is_active'] ?? true),
            ];

            if (! $location) {
                return Location::query()->create($attributes);
            }

            if ($location->code !== $attributes['code']) {
                throw ValidationException::withMessages(['code' => 'Lokasyon kodu kayıt sonrası değiştirilemez.']);
            }

            unset($attributes['code']);

            return $location->updateWithVersion($attributes, $expectedVersion ?? (int) $location->version);
        });
    }
}
