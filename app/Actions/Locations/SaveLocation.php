<?php

namespace App\Actions\Locations;

use App\Enums\LocationKind;
use App\Models\Period\Location;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Period\PeriodContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveLocation
{
    /** @param array<string, mixed> $data */
    public function handle(array $data, ?Location $location = null, ?int $expectedVersion = null): Location
    {
        MutationAuthorizer::authorize($location ? 'locations.update' : 'locations.create');
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
            $isFirstLocation = ! Location::query()->exists();
            $isDefault = $isFirstLocation ? true : (bool) ($data['is_default'] ?? false);
            $isActive = (bool) ($data['is_active'] ?? true);

            if ($isFirstLocation && $kind !== LocationKind::Warehouse) {
                throw ValidationException::withMessages([
                    'kind' => 'İlk lokasyon depo olmalıdır.',
                ]);
            }

            if (! Location::query()->where('kind', LocationKind::Warehouse->value)->where('is_active', true)
                ->when($location, fn ($query) => $query->whereKeyNot($location->id))
                ->exists()
                && $kind !== LocationKind::Warehouse) {
                throw ValidationException::withMessages([
                    'kind' => 'En az bir aktif depo lokasyonu bulunmalıdır.',
                ]);
            }

            if ($location?->kind === LocationKind::Warehouse
                && ($kind !== LocationKind::Warehouse || ! $isActive)
                && ! Location::query()
                    ->whereKeyNot($location->id)
                    ->where('kind', LocationKind::Warehouse->value)
                    ->where('is_active', true)
                    ->exists()) {
                throw ValidationException::withMessages([
                    'kind' => 'Son aktif depo pasife alınamaz veya türü değiştirilemez.',
                ]);
            }

            if ($location?->is_default && (! $isDefault || ! $isActive)) {
                throw ValidationException::withMessages([
                    'is_default' => 'Varsayılan lokasyonu doğrudan kaldıramazsınız. Önce başka lokasyonu varsayılan yapın.',
                ]);
            }

            if ($isDefault && ! $isActive) {
                throw ValidationException::withMessages([
                    'is_active' => 'Varsayılan lokasyon aktif olmalıdır.',
                ]);
            }

            if ($isDefault) {
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
                'is_default' => $isDefault,
                'is_active' => $isActive,
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
