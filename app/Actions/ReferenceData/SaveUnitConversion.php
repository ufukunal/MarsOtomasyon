<?php

namespace App\Actions\ReferenceData;

use App\Models\Period\UnitConversion;
use Illuminate\Validation\ValidationException;

final class SaveUnitConversion
{
    public function handle(array $data, ?UnitConversion $conversion = null, ?int $expectedVersion = null): UnitConversion
    {
        $factor = bcadd((string) $data['factor'], '0', 6);

        if (bccomp($factor, '0', 6) <= 0) {
            throw ValidationException::withMessages(['factor' => 'Dönüşüm katsayısı pozitif olmalıdır.']);
        }

        if ((int) $data['from_unit_id'] === (int) $data['to_unit_id']) {
            throw ValidationException::withMessages(['to_unit_id' => 'Kaynak ve hedef birim farklı olmalıdır.']);
        }

        $attributes = [
            'from_unit_id' => (int) $data['from_unit_id'],
            'to_unit_id' => (int) $data['to_unit_id'],
            'factor' => $factor,
        ];

        return $conversion
            ? $conversion->updateWithVersion($attributes, $expectedVersion ?? (int) $conversion->version)
            : UnitConversion::query()->create($attributes);
    }
}
