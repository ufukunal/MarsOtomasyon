<?php

namespace App\Modules\Accounts;

use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Products\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class AccountB2BProductVisibilityController
{
    public function __construct(private ActiveCompanyContext $companyContext) {}

    public function update(Request $request, int $account, int $product): RedirectResponse
    {
        $accountModel = $this->account($account);
        $productModel = $this->product($product);
        $validated = $request->validate(['is_visible' => ['required', 'boolean']]);

        DB::table('account_b2b_product_visibilities')->updateOrInsert(
            [
                'company_id' => $this->companyId(),
                'account_id' => $accountModel->getKey(),
                'product_id' => $productModel->getKey(),
            ],
            [
                'is_visible' => (bool) $validated['is_visible'],
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return back()->with('status', 'B2B ürün görünürlüğü güncellendi.');
    }

    public function destroy(int $account, int $product): RedirectResponse
    {
        $accountModel = $this->account($account);
        $productModel = $this->product($product);

        DB::table('account_b2b_product_visibilities')
            ->where('company_id', $this->companyId())
            ->where('account_id', $accountModel->getKey())
            ->where('product_id', $productModel->getKey())
            ->delete();

        return back()->with('status', 'B2B ürün görünürlüğü varsayılan politikaya döndürüldü.');
    }

    private function account(int $id): Account
    {
        return Account::query()->where('company_id', $this->companyId())->findOrFail($id);
    }

    private function product(int $id): Product
    {
        return Product::query()->where('company_id', $this->companyId())->findOrFail($id);
    }

    private function companyId(): int
    {
        $companyId = $this->companyContext->requireCompany()->getKey();
        if (! is_int($companyId)) {
            throw new LogicException('B2B product visibility requires a persisted active company.');
        }

        return $companyId;
    }
}
