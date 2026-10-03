<?php

namespace App\Livewire\Pages\Catalog;

use App\Actions\ReferenceData\SaveUnit;
use App\Actions\ReferenceData\SaveUnitConversion;
use App\Models\Period\Unit;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class UnitForm extends Component
{
    public ?Unit $unit = null;

    public string $code = '';

    public string $name = '';

    public bool $isBase = false;

    public bool $isActive = true;

    public int $version = 1;

    public ?int $toUnitId = null;

    public string $factor = '1.000000';

    public function mount(?Unit $unit = null): void
    {
        abort_unless(auth()->user()?->can($unit ? 'units.update' : 'units.create'), 403);
        $this->unit = $unit;

        if ($unit) {
            $this->code = $unit->code;
            $this->name = $unit->name;
            $this->isBase = (bool) $unit->is_base;
            $this->isActive = (bool) $unit->is_active;
            $this->version = (int) $unit->version;
        }
    }

    public function save(SaveUnit $action): void
    {
        $data = $this->validate([
            'code' => ['required', 'max:20'],
            'name' => ['required', 'max:255'],
        ]);

        $this->unit = $action->handle([
            'code' => $data['code'],
            'name' => $data['name'],
            'is_base' => $this->isBase,
            'is_active' => $this->isActive,
        ], $this->unit, $this->version);

        $this->version = (int) $this->unit->version;
    }

    public function addConversion(SaveUnitConversion $action): void
    {
        abort_unless($this->unit, 422);

        $this->validate([
            'toUnitId' => ['required', 'integer'],
            'factor' => ['required', 'decimal:0,6'],
        ]);

        $action->handle([
            'from_unit_id' => $this->unit->id,
            'to_unit_id' => $this->toUnitId,
            'factor' => $this->factor,
        ]);

        $this->toUnitId = null;
        $this->factor = '1.000000';
    }

    public function render(): View
    {
        return view('livewire.pages.catalog.unit-form', [
            'units' => Unit::query()->where('is_active', true)->when($this->unit, fn ($q) => $q->whereKeyNot($this->unit->id))->orderBy('name')->get(),
            'conversions' => $this->unit?->conversionsFrom()->with('toUnit')->get() ?? collect(),
        ])->layout('layouts.app', ['pageTitle' => $this->unit ? "Birim · {$this->code}" : 'Yeni Birim']);
    }
}
