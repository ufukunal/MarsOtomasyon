<?php

namespace App\Livewire\Products;

use App\Actions\Products\ReorderProductImages;
use App\Actions\Products\StoreProductImage;
use App\Models\Period\Product;
use App\Support\Products\ProductImageResolver;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProductImages extends Component
{
    use WithFileUploads;

    public Product $product;
    public string $collection = 'Ortak';
    public $image;

    public function upload(StoreProductImage $action): void
    {
        $this->authorize('update', $this->product);

        $this->validate([
            'image' => ['required', 'image', 'max:25600', 'mimes:jpg,jpeg,png,webp'],
            'collection' => ['required', 'string'],
        ]);

        $action->handle($this->product, $this->image, $this->collection);
        $this->reset('image');
    }

    public function delete(int $attachmentId): void
    {
        $this->authorize('update', $this->product);

        $attachment = $this->product->attachments()
            ->whereKey($attachmentId)
            ->firstOrFail();

        $attachment->delete();
    }

    public function reorder(array $attachmentIds, ReorderProductImages $action): void
    {
        $this->authorize('update', $this->product);

        $action->handle(
            $this->product,
            $this->collection,
            array_map('intval', $attachmentIds),
        );
    }

    public function move(int $attachmentId, string $direction, ReorderProductImages $action): void
    {
        $this->authorize('update', $this->product);

        $ids = $this->product->attachments()
            ->where('collection', $this->collection)
            ->orderBy('sort_order')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $index = array_search($attachmentId, $ids, true);

        if ($index === false) {
            return;
        }

        $target = $direction === 'up' ? $index - 1 : $index + 1;

        if ($target < 0 || $target >= count($ids)) {
            return;
        }

        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        $action->handle($this->product, $this->collection, $ids);
    }

    public function render(ProductImageResolver $resolver): View
    {
        $directCount = $this->product->attachments()
            ->where('collection', $this->collection)
            ->count();

        $fallback = (string) config('product_images.fallback', 'Ortak');
        $usingFallback = $directCount === 0 && $this->collection !== $fallback;

        return view('livewire.products.product-images', [
            'collections' => config('product_images.collections', []),
            'images' => $resolver->forCollection($this->product, $this->collection),
            'usingFallback' => $usingFallback,
        ]);
    }
}
