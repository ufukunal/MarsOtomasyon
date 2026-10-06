<?php

namespace App\Livewire\Channels;

use App\Actions\Channels\SaveChannelProductListing;
use App\Actions\Channels\SyncChannelListing;
use App\Enums\LocationKind;
use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period\ChannelProductListing;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Models\SalesChannelAccount;
use App\Support\Channels\ChannelAdapterResolver;
use App\Support\Channels\ChannelPriceResolver;
use App\Support\Channels\ChannelStockResolver;
use App\Support\Period\PeriodContext;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use JsonException;
use Livewire\Component;

class ChannelListingCenter extends Component
{
    use WithIdempotentMutations;

    public ?int $selectedListingId = null;

    public int $version = 1;

    public ?int $channelAccountId = null;

    public ?int $productId = null;

    public string $externalProductId = '';

    public string $externalListingId = '';

    public string $externalSku = '';

    public string $stockMode = '';

    public string $maxChannelQuantity = '';

    public string $withholdQuantity = '0.000';

    public string $fixedQuantity = '';

    public string $manualQuantity = '';

    public string $leadTimeDays = '';

    public string $priceOverride = '';

    public string $titleOverride = '';

    public string $descriptionOverride = '';

    public string $imageCollection = '';

    public string $categoryMetadataJson = '{}';

    public bool $isActive = true;

    /** @var list<int> */
    public array $locationIds = [];

    public function mount(): void
    {
        $this->seedMutationKeys(['save', 'publish', 'content', 'stock', 'price']);
        abort_unless(auth()->user()?->can('channel_listings.view'), 403);
        PeriodContext::ensure();
    }

    public function newListing(): void
    {
        $this->selectedListingId = null;
        $this->version = 1;
        $this->channelAccountId = null;
        $this->productId = null;
        $this->externalProductId = '';
        $this->externalListingId = '';
        $this->externalSku = '';
        $this->stockMode = '';
        $this->maxChannelQuantity = '';
        $this->withholdQuantity = '0.000';
        $this->fixedQuantity = '';
        $this->manualQuantity = '';
        $this->leadTimeDays = '';
        $this->priceOverride = '';
        $this->titleOverride = '';
        $this->descriptionOverride = '';
        $this->imageCollection = '';
        $this->categoryMetadataJson = '{}';
        $this->isActive = true;
        $this->locationIds = [];
    }

    public function selectListing(int $id): void
    {
        $listing = ChannelProductListing::query()
            ->with('locations')
            ->findOrFail($id);

        $this->selectedListingId = (int) $listing->id;
        $this->version = (int) $listing->version;
        $this->channelAccountId = (int) $listing->channel_account_id;
        $this->productId = (int) $listing->product_id;
        $this->externalProductId = (string) ($listing->external_product_id ?? '');
        $this->externalListingId = (string) ($listing->external_listing_id ?? '');
        $this->externalSku = (string) ($listing->external_sku ?? '');
        $this->stockMode = (string) ($listing->stock_mode ?? '');
        $this->maxChannelQuantity = (string) ($listing->max_channel_quantity ?? '');
        $this->withholdQuantity = (string) $listing->withhold_quantity;
        $this->fixedQuantity = (string) ($listing->fixed_quantity ?? '');
        $this->manualQuantity = (string) ($listing->manual_quantity ?? '');
        $this->leadTimeDays = $listing->lead_time_days === null ? '' : (string) $listing->lead_time_days;
        $this->priceOverride = (string) ($listing->price_override ?? '');
        $this->titleOverride = (string) ($listing->title_override ?? '');
        $this->descriptionOverride = (string) ($listing->description_override ?? '');
        $this->imageCollection = (string) ($listing->image_collection ?? '');
        $this->categoryMetadataJson = json_encode(
            $listing->category_metadata ?? new \stdClass,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ) ?: '{}';
        $this->isActive = (bool) $listing->is_active;
        $this->locationIds = $listing->locations
            ->pluck('location_id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    public function save(SaveChannelProductListing $action): void
    {
        $this->validate([
            'channelAccountId' => ['required', 'integer'],
            'productId' => ['required', 'integer'],
            'externalProductId' => ['nullable', 'string', 'max:255'],
            'externalListingId' => ['nullable', 'string', 'max:255'],
            'externalSku' => ['nullable', 'string', 'max:255'],
            'stockMode' => ['nullable', 'in:stock,production,manual'],
            'maxChannelQuantity' => ['nullable', 'decimal:0,3'],
            'withholdQuantity' => ['required', 'decimal:0,3'],
            'fixedQuantity' => ['nullable', 'decimal:0,3'],
            'manualQuantity' => ['nullable', 'decimal:0,3'],
            'leadTimeDays' => ['nullable', 'integer', 'min:0'],
            'priceOverride' => ['nullable', 'decimal:0,4'],
            'locationIds' => ['array'],
            'locationIds.*' => ['integer'],
        ]);

        abort_unless($this->channelAccountId !== null && $this->productId !== null, 422);
        $metadata = $this->decodeMetadata();
        $existing = $this->selectedListingId
            ? ChannelProductListing::query()->findOrFail($this->selectedListingId)
            : null;

        $saved = $this->runPeriodMutation('save', fn () => $action->handle(
            channelAccountId: $this->channelAccountId,
            productId: $this->productId,
            locationIds: $this->locationIds,
            data: [
                'external_product_id' => $this->externalProductId,
                'external_listing_id' => $this->externalListingId,
                'external_sku' => $this->externalSku,
                'stock_mode' => $this->stockMode,
                'max_channel_quantity' => $this->maxChannelQuantity,
                'withhold_quantity' => $this->withholdQuantity,
                'fixed_quantity' => $this->fixedQuantity,
                'manual_quantity' => $this->manualQuantity,
                'lead_time_days' => $this->leadTimeDays,
                'price_override' => $this->priceOverride,
                'title_override' => $this->titleOverride,
                'description_override' => $this->descriptionOverride,
                'image_collection' => $this->imageCollection,
                'category_metadata' => $metadata,
                'is_active' => $this->isActive,
            ],
            listing: $existing,
            expectedVersion: $existing ? $this->version : null,
        ));

        $this->selectListing((int) $saved->id);
        session()->flash('status', 'Kanal listing mapping kaydedildi.');
    }

    public function publish(SyncChannelListing $action): void
    {
        $listing = $this->currentListing();
        $this->runPeriodMutation('publish', fn () => $action->publish($listing));
        session()->flash('status', 'Listing publish işlemi kanala gönderildi.');
    }

    public function syncContent(SyncChannelListing $action): void
    {
        $listing = $this->currentListing();
        $this->runPeriodMutation('content', fn () => $action->content($listing));
        session()->flash('status', 'İçerik/görsel sync işlemi kanala gönderildi.');
    }

    public function syncStock(SyncChannelListing $action): void
    {
        $listing = $this->currentListing();
        $this->runPeriodMutation('stock', fn () => $action->stock($listing));
        session()->flash('status', 'Stok sync işlemi kanala gönderildi.');
    }

    public function syncPrice(SyncChannelListing $action): void
    {
        $listing = $this->currentListing();
        $this->runPeriodMutation('price', fn () => $action->price($listing));
        session()->flash('status', 'Fiyat sync işlemi kanala gönderildi.');
    }

    public function render(
        ChannelAdapterResolver $adapters,
        ChannelStockResolver $stock,
        ChannelPriceResolver $prices,
    ): View {
        $accountIds = SalesChannelAccount::query()
            ->where('company_id', PeriodContext::companyId())
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return view('livewire.channels.channel-listing-center', [
            'listings' => ChannelProductListing::query()
                ->with('product')
                ->whereIn('channel_account_id', $accountIds)
                ->orderBy('channel_account_id')
                ->orderBy('product_id')
                ->get(),
            'accounts' => SalesChannelAccount::query()
                ->where('company_id', PeriodContext::companyId())
                ->orderBy('platform')
                ->orderBy('name')
                ->get(),
            'products' => Product::query()
                ->orderBy('name')
                ->limit(1500)
                ->get(),
            'locations' => Location::query()
                ->where('is_active', true)
                ->where('kind', '!=', LocationKind::Subcontractor->value)
                ->orderBy('name')
                ->get(),
            'adapterAvailable' => $this->channelAccountId
                ? $adapters->hasAdapter(SalesChannelAccount::query()->find($this->channelAccountId)?->platform?->value ?? '')
                : false,
            'stockPreview' => $this->selectedListingId
                ? $this->safePreview(fn () => $stock->quantity(
                    ChannelProductListing::query()->findOrFail($this->selectedListingId),
                ))
                : null,
            'pricePreview' => $this->selectedListingId
                ? $this->safePreview(fn () => $prices->price(
                    ChannelProductListing::query()->findOrFail($this->selectedListingId),
                ))
                : null,
        ])->layout('layouts.app', ['pageTitle' => 'Kanal Ürün Listingleri']);
    }

    private function safePreview(callable $callback): ?string
    {
        try {
            return (string) $callback();
        } catch (\Throwable) {
            return null;
        }
    }

    private function currentListing(): ChannelProductListing
    {
        abort_unless($this->selectedListingId !== null, 422);

        return ChannelProductListing::query()->findOrFail($this->selectedListingId);
    }

    /** @return array<string, mixed> */
    private function decodeMetadata(): array
    {
        try {
            $decoded = json_decode(
                $this->categoryMetadataJson ?: '{}',
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'categoryMetadataJson' => 'Kategori metadata geçerli JSON olmalıdır.',
            ]);
        }

        if (! is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw ValidationException::withMessages([
                'categoryMetadataJson' => 'Kategori metadata JSON object olmalıdır.',
            ]);
        }

        return $decoded;
    }
}
