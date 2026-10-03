<?php

namespace App\Livewire\Pages\Catalog;

use App\Models\Period\ProductCategory;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class CategoryList extends Component
{
    public function mount(): void
    {
        abort_unless(auth()->user()?->can('product_categories.view'), 403);
    }

    public function render(): View
    {
        return view('livewire.pages.catalog.category-list', [
            'roots' => ProductCategory::query()
                ->whereNull('parent_id')
                ->with(['children.children'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ])->layout('layouts.app', ['pageTitle' => 'Kategoriler']);
    }
}
