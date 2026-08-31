<?php

namespace App\Modules\B2B\Portal;

use App\Modules\B2B\Enums\B2BPermission;
use App\Modules\Products\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final readonly class B2BCatalogController
{
    public function __construct(private B2BPortalAccess $access, private B2BPriceCalculator $prices) {}

    public function index(Request $request): View
    {
        $user = $this->access->user();
        $account = $this->access->account();
        $policy = $this->access->policy();
        $search = trim((string) $request->query('q', ''));
        $query = Product::query()->where('company_id', $user->company_id)->where('status', 'active')->orderBy('code');
        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('code', 'ilike', '%'.$search.'%')->orWhere('name', 'ilike', '%'.$search.'%');
            });
        }
        $products = $query->paginate(30)->withQueryString();
        $showPrice = $this->access->can(B2BPermission::ViewPrices);
        $showStock = $this->access->can(B2BPermission::ViewStock);
        $rows = [];

        foreach ($products as $product) {
            $stock = null;
            if ($showStock) {
                $stockQuery = DB::table('stock_balances')
                    ->where('company_id', $user->company_id)
                    ->where('product_id', $product->getKey());
                if ($policy->default_warehouse_id !== null) {
                    $stockQuery->where('warehouse_id', $policy->default_warehouse_id);
                }
                $stockRow = $stockQuery->selectRaw('COALESCE(SUM(available_quantity), 0)::text AS available')->first();
                $stock = (string) (((array) ($stockRow ?? []))['available'] ?? '0');
            }
            $rows[(int) $product->getKey()] = [
                'price' => $showPrice ? $this->prices->netPrice($product, $account) : null,
                'stock' => $stock,
            ];
        }

        return view('b2b.catalog.index', compact('products', 'rows', 'search', 'showPrice', 'showStock', 'account'));
    }
}
