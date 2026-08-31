<?php

namespace App\Modules\B2B\Portal;

use App\Modules\B2B\Enums\B2BPermission;
use App\Modules\Products\Models\Product;
use App\Modules\Quotes\Pricing\Decimal6;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

final readonly class B2BCartController
{
    private const string SESSION_KEY = 'b2b_cart';

    public function __construct(private B2BPortalAccess $access, private B2BPriceCalculator $priceCalculator) {}

    public function index(Request $request): View
    {
        $cart = $this->cart($request);
        $products = Product::query()
            ->where('company_id', $this->access->user()->company_id)
            ->whereIn('code', array_keys($cart))
            ->where('status', 'active')
            ->orderBy('code')
            ->get();
        $showPrice = $this->access->can(B2BPermission::ViewPrices);
        $prices = [];
        if ($showPrice) {
            $account = $this->access->account();
            foreach ($products as $product) {
                $prices[(string) $product->code] = $this->priceCalculator->netPrice($product, $account);
            }
        }

        return view('b2b.cart.index', [
            'cart' => $cart, 'products' => $products, 'showPrice' => $showPrice, 'prices' => $prices,
            'idempotencyKey' => (string) Str::ulid(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['product_code' => ['required', 'string', 'max:64'], 'quantity' => ['required', 'string', 'max:32']]);
        $code = trim((string) $validated['product_code']);
        Product::query()->where('company_id', $this->access->user()->company_id)->where('code', $code)->where('status', 'active')->firstOrFail();
        $cart = $this->cart($request);
        $cart[$code] = Decimal6::positive((string) $validated['quantity'], 'quantity')->value();
        ksort($cart);
        $request->session()->put(self::SESSION_KEY, $cart);

        return back()->with('status', 'Ürün sepete eklendi.');
    }

    public function update(Request $request, string $productCode): RedirectResponse
    {
        $validated = $request->validate(['quantity' => ['required', 'string', 'max:32']]);
        $cart = $this->cart($request);
        abort_unless(array_key_exists($productCode, $cart), 404);
        $cart[$productCode] = Decimal6::positive((string) $validated['quantity'], 'quantity')->value();
        $request->session()->put(self::SESSION_KEY, $cart);

        return back()->with('status', 'Sepet güncellendi.');
    }

    public function destroy(Request $request, string $productCode): RedirectResponse
    {
        $cart = $this->cart($request);
        unset($cart[$productCode]);
        $request->session()->put(self::SESSION_KEY, $cart);

        return back()->with('status', 'Ürün sepetten çıkarıldı.');
    }

    /** @return array<string, string> */
    private function cart(Request $request): array
    {
        $raw = $request->session()->get(self::SESSION_KEY, []);
        if (! is_array($raw)) {
            return [];
        }
        $cart = [];
        foreach ($raw as $code => $quantity) {
            if (is_string($code) && is_string($quantity)) {
                $cart[$code] = $quantity;
            }
        }

        return $cart;
    }
}
