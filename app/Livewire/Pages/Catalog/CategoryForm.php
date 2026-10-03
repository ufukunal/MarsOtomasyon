<?php

namespace App\Livewire\Pages\Catalog;

use App\Actions\Catalog\SaveProductCategory;
use App\Models\Period\ProductCategory;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class CategoryForm extends Component
{
    public ?ProductCategory $category = null;
    public string $name = '';
    public ?int $parentId = null;
    public int $sortOrder = 0;
    public bool $isActive = true;
    public int $version = 1;

    public function mount(?ProductCategory $category = null): void
    {
        abort_unless(auth()->user()?->can($category ? 'product_categories.update' : 'product_categories.create'), 403);
        $this->category = $category;

        if ($category) {
            $this->name = $category->name;
            $this->parentId = $category->parent_id;
            $this->sortOrder = (int) $category->sort_order;
            $this->isActive = (bool) $category->is_active;
            $this->version = (int) $category->version;
        }
    }

    public function save(SaveProductCategory $action): void
    {
        $this->validate(['name'=>['required','max:255'],'parentId'=>['nullable','integer']]);

        $this->category = $action->handle([
            'name'=>$this->name,
            'parent_id'=>$this->parentId,
            'sort_order'=>$this->sortOrder,
            'is_active'=>$this->isActive,
        ], $this->category, $this->version);

        $this->version=(int)$this->category->version;
    }

    public function render(): View
    {
        return view('livewire.pages.catalog.category-form',[
            'categories'=>ProductCategory::query()->when($this->category,fn($q)=>$q->whereKeyNot($this->category->id))->orderBy('name')->get(),
        ])->layout('layouts.app',['pageTitle'=>$this->category?'Kategori Düzenle':'Yeni Kategori']);
    }
}
