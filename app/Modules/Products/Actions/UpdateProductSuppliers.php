<?php

namespace App\Modules\Products\Actions;

use App\Modules\Accounts\Enums\AccountStatus;
use App\Modules\Accounts\Enums\AccountType;
use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Products\Models\Product;
use App\Modules\Products\Models\ProductSupplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class UpdateProductSuppliers
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private AuditRecorder $audit,
    ) {}

    /** @param list<int> $supplierAccountIds */
    public function handle(int $productId, array $supplierAccountIds): Product
    {
        $companyId = $this->companyId();
        $desired = array_values(array_unique($supplierAccountIds));
        $desired = array_values(array_filter($desired, static fn (int $id): bool => $id > 0));
        sort($desired);

        return DB::transaction(function () use ($companyId, $productId, $desired): Product {
            $product = Product::query()
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->findOrFail($productId);

            $existing = ProductSupplier::query()
                ->where('company_id', $companyId)
                ->where('product_id', $productId)
                ->pluck('account_id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->values()
                ->all();
            sort($existing);

            $this->assertSupplierAccounts($companyId, $desired, $existing);

            if ($desired !== $existing) {
                $relations = ProductSupplier::query()
                    ->where('company_id', $companyId)
                    ->where('product_id', $productId);
                if ($desired === []) {
                    $relations->delete();
                } else {
                    $relations->whereNotIn('account_id', $desired)->delete();
                }

                foreach (array_values(array_diff($desired, $existing)) as $accountId) {
                    ProductSupplier::query()->create([
                        'company_id' => $companyId,
                        'product_id' => $productId,
                        'account_id' => $accountId,
                    ]);
                }

                $this->audit->record(
                    AuditAction::ProductSuppliersUpdated,
                    AuditTargetType::Product,
                    $product->getKey(),
                    before: ['supplier_account_ids' => $existing],
                    after: ['supplier_account_ids' => $desired],
                );
            }

            return $product->load('supplierRelations.account');
        });
    }

    /** @param list<int> $desired @param list<int> $existing */
    private function assertSupplierAccounts(int $companyId, array $desired, array $existing): void
    {
        if ($desired === []) {
            return;
        }

        $accounts = Account::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $desired)
            ->whereIn('type', [AccountType::Supplier->value, AccountType::Mixed->value])
            ->get();

        if ($accounts->count() !== count($desired)) {
            throw ValidationException::withMessages([
                'supplier_ids' => 'Seçilen tedarikçiler aktif şirkete ait Supplier veya Mixed cari kayıtları olmalıdır.',
            ]);
        }

        foreach ($accounts as $account) {
            $accountId = $account->getKey();
            if (! is_int($accountId)) {
                throw new LogicException('Supplier account persistence did not return an integer key.');
            }
            if ($account->statusEnum() === AccountStatus::Inactive && ! in_array($accountId, $existing, true)) {
                throw ValidationException::withMessages([
                    'supplier_ids' => 'Pasif bir cari yeni tedarikçi ilişkisi olarak eklenemez.',
                ]);
            }
        }
    }

    private function companyId(): int
    {
        $companyId = $this->companyContext->requireCompany()->getKey();

        return is_int($companyId)
            ? $companyId
            : throw new LogicException('Product supplier management requires a persisted active company.');
    }
}
