<?php

namespace App\Livewire\Components;

use App\Contracts\SearchIndexed;
use App\Models\PeriodModel;
use App\Support\Period\PeriodContext;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
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

    /** @var list<array{id:int|string, code:string, label:string}> */
    public array $results = [];

    /** @var list<array{id:int|string, code:string, label:string}> */
    public array $detailedResults = [];

    public int $highlighted = -1;

    public bool $detailedOpen = false;

    public function mount(): void
    {
        if (is_subclass_of($this->model, PeriodModel::class)) {
            PeriodContext::ensure();
        }
    }

    public function updatedQuery(): void
    {
        $this->results = $this->resultArray($this->search()->limit(20)->get());
        $this->highlighted = $this->results === [] ? -1 : 0;
    }

    public function chooseExact(): void
    {
        $builder = ($this->model)::query()->where($this->codeField, $this->query);

        if ($this->barcodeField) {
            $builder->orWhere($this->barcodeField, $this->query);
        }

        $exact = $builder->first();

        if ($exact) {
            $this->select($exact->getKey());

            return;
        }

        $this->chooseHighlighted();
    }

    public function chooseHighlighted(): void
    {
        if ($this->highlighted >= 0 && isset($this->results[$this->highlighted])) {
            $this->select($this->results[$this->highlighted]['id']);

            return;
        }

        $this->updatedQuery();
    }

    public function moveHighlight(int $delta): void
    {
        $count = count($this->results);

        if ($count === 0) {
            $this->highlighted = -1;

            return;
        }

        $this->highlighted = ($this->highlighted + $delta + $count) % $count;
    }

    public function closeResults(): void
    {
        $this->results = [];
        $this->highlighted = -1;
        $this->detailedOpen = false;
    }

    public function openDetailed(): void
    {
        $this->detailedResults = $this->resultArray($this->search()->limit(100)->get());
        $this->detailedOpen = true;
    }

    public function select(int|string $id): void
    {
        $model = ($this->model)::query()->findOrFail($id);

        $this->value = $model->getKey();
        $this->query = (string) data_get($model, $this->labelField);
        $this->closeResults();

        $this->dispatch('lookup-selected', id: $model->getKey());
    }

    /** @return Builder<Model> */
    private function search(): Builder
    {
        $builder = ($this->model)::query();

        if ($this->query === '') {
            return $builder->whereRaw('1 = 0');
        }

        $model = $builder->getModel();

        if ($model instanceof SearchIndexed) {
            return $model->scopeSearch($builder, $this->query);
        }

        return $builder->where(function ($query): void {
            $query->where($this->codeField, 'ilike', '%'.$this->query.'%')
                ->orWhere($this->labelField, 'ilike', '%'.$this->query.'%');
        });
    }

    /**
     * @param Collection<int, Model> $rows
     * @return list<array{id:int|string, code:string, label:string}>
     */
    private function resultArray(Collection $rows): array
    {
        return $rows->map(fn (Model $row) => [
            'id' => $row->getKey(),
            'code' => (string) data_get($row, $this->codeField),
            'label' => (string) data_get($row, $this->labelField),
        ])->all();
    }

    public function render(): View
    {
        return view('livewire.components.lookup');
    }
}
