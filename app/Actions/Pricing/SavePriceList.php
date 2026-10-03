<?php

namespace App\Actions\Pricing;

use App\Models\Period\PriceList;
use Illuminate\Support\Facades\DB;

final class SavePriceList
{
    public function handle(
        array $data,
        ?PriceList $list = null,
        ?int $expectedVersion = null,
    ): PriceList {
        return DB::connection('period')->transaction(function () use ($data, $list, $expectedVersion): PriceList {
            $isFirst = ! PriceList::query()->exists();
            $isDefault = $isFirst ? true : (bool) ($data['is_default'] ?? false);
            $isActive = (bool) ($data['is_active'] ?? true);

            if ($list?->is_default && (! $isDefault || ! $isActive)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'is_default' => 'Varsayılan fiyat listesini doğrudan kaldıramazsınız. Önce başka listeyi varsayılan yapın.',
                ]);
            }

            if ($isDefault && ! $isActive) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'is_active' => 'Varsayılan fiyat listesi aktif olmalıdır.',
                ]);
            }

            if ($isDefault) {
                PriceList::query()
                    ->when($list, fn ($q) => $q->whereKeyNot($list->id))
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $attributes = [
                'name' => trim((string) $data['name']),
                'currency' => strtoupper((string) ($data['currency'] ?? 'TRY')),
                'vat_included' => (bool) ($data['vat_included'] ?? false),
                'is_default' => $isDefault,
                'is_active' => $isActive,
            ];

            return $list
                ? $list->updateWithVersion($attributes, $expectedVersion ?? (int) $list->version)
                : PriceList::query()->create($attributes);
        });
    }
}
