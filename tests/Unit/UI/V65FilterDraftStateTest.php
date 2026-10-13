<?php

use App\Livewire\Concerns\WithV65FilterDrawer;

it('keeps draft changes out of the applied list until explicit apply', function (): void {
    $screen = new class {
        use WithV65FilterDrawer;
        public string $search = 'önceki';
        public array $filterValues = ['active' => '1'];
        public string $sort = 'id';
        public string $direction = 'asc';
        public int $pageResets = 0;

        protected function v65FilterDefinitions(): array
        {
            return [
                'active' => ['type' => 'select', 'label' => 'Durum', 'options' => ['1' => 'Aktif', '0' => 'Pasif']],
                'price' => ['type' => 'number-range', 'label' => 'Fiyat'],
            ];
        }

        protected function v65SortOptions(): array
        {
            return ['code' => 'Kod'];
        }

        public function resetPage(): void
        {
            $this->pageResets++;
        }
    };

    $screen->openV65Filters();
    $screen->v65DraftFilters['search'] = 'yeni';
    $screen->v65DraftFilters['filters']['active'] = '0';

    expect($screen->search)->toBe('önceki')
        ->and($screen->filterValues['active'])->toBe('1');

    $screen->closeV65Filters();
    expect($screen->search)->toBe('önceki');

    $screen->openV65Filters();
    $screen->v65DraftFilters['search'] = 'yeni';
    $screen->v65DraftFilters['filters']['active'] = '0';
    $screen->applyV65Filters();

    expect($screen->search)->toBe('yeni')
        ->and($screen->filterValues['active'])->toBe('0')
        ->and($screen->pageResets)->toBe(1)
        ->and($screen->activeV65FilterCount())->toBe(2);

    $screen->removeV65Filter('filter:active');
    expect($screen->filterValues)->toBe([])
        ->and($screen->search)->toBe('yeni');

    $screen->clearV65Filters();
    expect($screen->search)->toBe('')
        ->and($screen->filterValues)->toBe([])
        ->and($screen->direction)->toBe('asc');
});


it('validates numeric bounds before applying a range', function (): void {
    $screen = new class {
        use \App\Livewire\Concerns\WithV65FilterDrawer;
        public string $search = '';
        public array $filterValues = [];
        public string $sort = 'id';
        public string $direction = 'asc';
        public function resetPage(): void {}
        protected function v65FilterDefinitions(): array
        {
            return ['price' => ['label' => 'Satış Fiyatı', 'type' => 'number-range']];
        }
        protected function v65SortOptions(): array { return []; }
    };

    $screen->openV65Filters();
    $screen->v65DraftFilters['filters']['price'] = ['min' => '20.0000', 'max' => '10.0000'];
    expect(fn () => $screen->applyV65Filters())->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect($screen->filterValues)->toBe([]);

    $screen->v65DraftFilters['filters']['price'] = ['min' => '0', 'max' => '10.5000'];
    $screen->applyV65Filters();
    expect($screen->filterValues['price'])->toBe(['min' => '0', 'max' => '10.5000']);
});
