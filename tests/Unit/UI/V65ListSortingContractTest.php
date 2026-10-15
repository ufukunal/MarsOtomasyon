<?php

use App\Livewire\Concerns\WithV65FilterDrawer;
use Symfony\Component\HttpKernel\Exception\HttpException;

it('rejects unlisted client sort columns and supports ascending/descending values', function (): void {
    $screen = new class
    {
        use WithV65FilterDrawer;

        public string $search = '';

        public array $filterValues = [];

        public string $sort = 'id';

        public string $direction = 'asc';

        public function resetPage(): void {}

        protected function v65FilterDefinitions(): array
        {
            return [];
        }

        protected function v65SortOptions(): array
        {
            return ['code' => 'Kod'];
        }
    };

    $screen->openV65Filters();
    $screen->v65DraftFilters['sort'] = 'DROP TABLE';
    expect(fn () => $screen->applyV65Filters())->toThrow(HttpException::class);
    expect($screen->sort)->toBe('id');

    $screen->v65DraftFilters['sort'] = 'code';
    $screen->v65DraftFilters['direction'] = 'desc';
    $screen->applyV65Filters();
    expect($screen->sort)->toBe('code')
        ->and($screen->direction)->toBe('desc');

    $screen->removeV65Filter('sort');
    expect($screen->sort)->toBe('id')
        ->and($screen->direction)->toBe('asc');
});

it('uses explicitly approved document, warehouse and simple-list sort keys', function (): void {
    $documents = file_get_contents(base_path('app/Livewire/Concerns/WithV65DocumentList.php'));
    $warehouse = file_get_contents(base_path('app/Livewire/Pages/Warehouse/TransportSlipList.php'));
    $simple = file_get_contents(base_path('app/Livewire/Concerns/WithV65SimpleListFilter.php'));

    expect($documents)->toContain("['document_date' => 'Tarih'")
        ->toContain('v65DocumentSort')
        ->and($warehouse)->toContain("['slip_date' => 'Tarih'")
        ->toContain('v65WarehouseSort')
        ->and($simple)->toContain("['id' => 'Kayıt Numarası']");
});
