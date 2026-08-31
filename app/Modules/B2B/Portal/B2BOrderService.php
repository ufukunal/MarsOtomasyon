<?php

namespace App\Modules\B2B\Portal;

use App\Modules\Accounts\Models\Account;
use App\Modules\B2B\Enums\B2BPermission;
use App\Modules\B2B\Enums\B2BRiskBehavior;
use App\Modules\B2B\Models\B2BUser;
use App\Modules\Core\Enums\AuditSource;
use App\Modules\Core\Models\TaxZeroReason;
use App\Modules\Products\Models\Product;
use App\Modules\Quotes\Pricing\PriceBasis;
use App\Modules\SalesOrders\Actions\CreateSalesOrder;
use App\Modules\SalesOrders\Actions\SalesOrderDraftData;
use App\Modules\SalesOrders\Actions\SalesOrderDraftResolver;
use App\Modules\SalesOrders\Actions\SalesOrderLineData;
use App\Modules\SalesOrders\Models\SalesOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class B2BOrderService
{
    public function __construct(
        private B2BPortalAccess $access,
        private SalesOrderDraftResolver $resolver,
        private CreateSalesOrder $createOrder,
        private B2BProductVisibility $visibility,
    ) {}

    /** @param array<string, string> $cart */
    public function submit(array $cart, string $idempotencyKey): B2BOrderResult
    {
        $this->access->authorize(B2BPermission::PlaceOrders);
        if ($cart === []) {
            throw ValidationException::withMessages(['cart' => 'Sepet boş olamaz.']);
        }
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '' || strlen($idempotencyKey) > 128) {
            throw ValidationException::withMessages(['idempotency_key' => 'Sipariş gönderim anahtarı geçersiz.']);
        }

        ksort($cart);
        $user = $this->access->user();
        $account = $this->access->account();
        $policy = $this->access->policy();
        $draft = $this->draft(
            $user,
            $account,
            $cart,
            $policy->default_warehouse_id === null ? null : (int) $policy->default_warehouse_id,
        );
        $resolved = $this->resolver->resolve((int) $user->company_id, $draft);
        $payloadHash = hash('sha256', json_encode($cart, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($user, $account, $policy, $draft, $resolved, $payloadHash, $idempotencyKey): B2BOrderResult {
            $lockKey = (int) $user->company_id.':'.(int) $user->getKey().':'.$idempotencyKey;
            DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [$lockKey]);

            $existing = DB::table('b2b_order_submissions')
                ->where('company_id', $user->company_id)
                ->where('b2b_user_id', $user->getKey())
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                if (! hash_equals((string) $existing->payload_hash, $payloadHash)) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => 'Aynı gönderim anahtarı farklı bir sepet için kullanılamaz.',
                    ]);
                }
                if ($existing->sales_order_id !== null) {
                    $order = SalesOrder::query()
                        ->where('company_id', $user->company_id)
                        ->where('account_id', $user->account_id)
                        ->findOrFail((int) $existing->sales_order_id);

                    return new B2BOrderResult($order, null, true);
                }
            } else {
                DB::table('b2b_order_submissions')->insert([
                    'company_id' => $user->company_id,
                    'b2b_user_id' => $user->getKey(),
                    'idempotency_key' => $idempotencyKey,
                    'payload_hash' => $payloadHash,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Account::query()
                ->where('company_id', $user->company_id)
                ->whereKey($account->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $balanceRow = DB::table('account_transactions')
                ->where('company_id', $user->company_id)
                ->where('account_id', $account->getKey())
                ->selectRaw('COALESCE(SUM(signed_amount), 0)::text AS balance')
                ->first();
            $balance = (string) (((array) ($balanceRow ?? []))['balance'] ?? '0');
            $riskRow = DB::selectOne(
                'SELECT CASE WHEN (GREATEST(?::numeric, 0) + ?::numeric) > ?::numeric THEN 1 ELSE 0 END AS exceeded',
                [$balance, $resolved->calculation->gross, (string) $account->risk_limit],
            );
            $exceeded = (int) $riskRow->exceeded === 1;
            $riskBehavior = B2BRiskBehavior::tryFrom((string) $policy->getRawOriginal('risk_behavior'))
                ?? throw new LogicException('Persisted B2B risk behavior is invalid.');
            if ($exceeded && $riskBehavior === B2BRiskBehavior::Block) {
                throw ValidationException::withMessages(['risk' => 'Cari risk limiti bu siparişi karşılamıyor.']);
            }
            $warning = $exceeded
                ? 'Cari risk limiti aşılıyor; politika uyarı modunda olduğu için sipariş oluşturuldu.'
                : null;

            $order = $this->createOrder->handle(
                $draft,
                'b2b',
                AuditSource::Api,
                [
                    'actor_type' => 'b2b_user',
                    'actor_public_id' => (string) $user->public_id,
                    'actor_account_id' => (int) $user->account_id,
                ],
            );
            DB::table('b2b_order_submissions')
                ->where('company_id', $user->company_id)
                ->where('b2b_user_id', $user->getKey())
                ->where('idempotency_key', $idempotencyKey)
                ->update(['sales_order_id' => $order->getKey(), 'updated_at' => now()]);

            return new B2BOrderResult($order, $warning, false);
        });
    }

    /** @param array<string, string> $cart */
    private function draft(B2BUser $user, Account $account, array $cart, ?int $defaultWarehouseId): SalesOrderDraftData
    {
        $query = Product::query()
            ->with('tax')
            ->where('company_id', $user->company_id)
            ->whereIn('code', array_keys($cart))
            ->where('status', 'active');
        $this->visibility->apply($query, (int) $user->company_id, (int) $user->account_id);
        $products = $query->get()->keyBy('code');
        if ($products->count() !== count($cart)) {
            throw ValidationException::withMessages(['cart' => 'Sepette artık aktif, görünür veya şirkete ait olmayan ürün var.']);
        }

        $lines = [];
        foreach ($cart as $code => $quantity) {
            $product = $products->get($code);
            if (! $product instanceof Product) {
                throw ValidationException::withMessages(['cart' => 'Sepet ürünü bulunamadı.']);
            }

            $allocationQuery = DB::table('stock_balances')
                ->where('company_id', $user->company_id)
                ->where('product_id', $product->getKey());
            if ($defaultWarehouseId !== null) {
                $allocationQuery->where('warehouse_id', $defaultWarehouseId);
            }
            $allocation = $allocationQuery
                ->whereRaw('available_quantity >= ?::numeric', [$quantity])
                ->select(['warehouse_id', 'location_id'])
                ->selectRaw('available_quantity::text AS available_quantity')
                ->orderByDesc('available_quantity')
                ->orderBy('warehouse_id')
                ->orderBy('location_id')
                ->first();
            if ($allocation === null) {
                throw ValidationException::withMessages([
                    'stock' => $code.' için yeterli kullanılabilir stok yok.',
                ]);
            }

            $zeroReasonId = null;
            $tax = $product->tax;
            if ($tax !== null && preg_match('/^0+(?:\.0+)?$/D', (string) $tax->rate) === 1) {
                $zeroReasonId = TaxZeroReason::query()
                    ->where('company_id', $user->company_id)
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->value('id');
                if ($zeroReasonId === null) {
                    throw ValidationException::withMessages([
                        'cart' => $code.' için KDV sıfır nedeni tanımlı değil.',
                    ]);
                }
            }

            $lines[] = new SalesOrderLineData(
                productId: (int) $product->getKey(),
                quantity: $quantity,
                unitPrice: (string) $product->sale_price_net,
                priceBasis: PriceBasis::Net,
                lineDiscountRate: (string) $account->discount_rate,
                taxZeroReasonId: $zeroReasonId === null ? null : (int) $zeroReasonId,
                description: (string) $product->name,
                warehouseId: (int) $allocation->warehouse_id,
                locationId: (int) $allocation->location_id,
            );
        }

        return new SalesOrderDraftData(
            accountId: (int) $account->getKey(),
            orderDate: now()->format('Y-m-d'),
            currencyCode: (string) $account->book_currency_code,
            documentDiscountRate: '0.000000',
            note: 'B2B / Bayi portal siparişi',
            lines: $lines,
        );
    }
}
