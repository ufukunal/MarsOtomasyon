<?php

namespace App\Livewire\Components;

use App\Models\PeriodModel;
use App\Support\Period\PeriodContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Modelable;
use Livewire\Component;

class Lookup extends Component
{
    /** @var class-string<Model> */
    public string $model;

    public string $labelField = 'name';
    public string $codeField = 'code';
    public ?string $barcodeField = null;
    public string $query = '';

    #[Modelable]
    public int|string|null $value = null;

    public array $results = [];

    public function mount(): void
    {
        if (is_subclass_of($this->model, PeriodModel::class)) {
            PeriodContext::ensure();
        }
    }

    public function updatedQuery(): void
    {
        $this->results = $this->search()->limit(20)->get()
            ->map(fn (Model $row) => [
                'id' => $row->getKey(),
                'code' => data_get($row, $this->codeField),
                'label' => data_get($row, $this->labelField),
            ])
            ->all();
    }

    public function chooseExact(): void
    {
        $builder = ($this->model)::query()
            ->where($this->codeField, $this->query);

        if ($this->barcodeField) {
            $builder->orWhere($this->barcodeField, $this->query);
        }

        $exact = $builder->first();

        if ($exact) {
            $this->select($exact->getKey());

            return;
        }

        $this->updatedQuery();
    }

    public function select(int|string $id): void
    {
        $model = ($this->model)::query()->findOrFail($id);

        $this->value = $model->getKey();
        $this->query = (string) data_get($model, $this->labelField);
        $this->results = [];

        $this->dispatch('lookup-selected', id: $model->getKey());
    }

    private function search()
    {
        $builder = ($this->model)::query();

        if ($this->query === '') {
            return $builder->whereRaw('1 = 0');
        }

        if (method_exists($builder->getModel(), 'scopeSearch')) {
            return $builder->search($this->query);
        }

        return $builder->where(function ($query): void {
            $query->where($this->codeField, 'ilike', '%'.$this->query.'%')
                ->orWhere($this->labelField, 'ilike', '%'.$this->query.'%');
        });
    }

    public function render(): View
    {
        return view('livewire.components.lookup');
    }
}
