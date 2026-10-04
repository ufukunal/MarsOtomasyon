<?php

namespace App\Actions\Stock;

use App\Enums\ProductKind;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Models\Period\WarehouseSlip;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Period\PeriodContext;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveWarehouseSlipDraft
{
    private const IN_REASONS = ['found', 'adjustment', 'sample_return', 'opening'];

    private const OUT_REASONS = ['scrap', 'broken', 'sample_issue', 'fixed_asset', 'adjustment'];

    /**
     * @param array{
     *   location_id:int,
     *   slip_date:string,
     *   direction:string,
     *   reason:string,
     *   note?:?string,
     *   lines:list<array{product_id:int,quantity:string,unit_cost:?string,note?:?string}>
     * } $data
     */
    public function handle(array $data, ?WarehouseSlip $slip = null): WarehouseSlip
    {
        MutationAuthorizer::authorize($slip ? 'warehouse_slips.update' : 'warehouse_slips.create');
        PeriodContext::ensureWritable();

        Location::query()->findOrFail($data['location_id']);
        CarbonImmutable::parse($data['slip_date']);

        if (! in_array($data['direction'], ['in', 'out'], true)) {
            throw ValidationException::withMessages(['direction' => 'Geçersiz ambar fişi yönü.']);
        }

        $allowedReasons = $data['direction'] === 'in' ? self::IN_REASONS : self::OUT_REASONS;

        if (! in_array($data['reason'], $allowedReasons, true)) {
            throw ValidationException::withMessages(['reason' => 'Seçilen sebep bu fiş yönü için geçerli değil.']);
        }

        if ($data['lines'] === []) {
            throw ValidationException::withMessages(['lines' => 'Ambar fişi en az bir satır içermelidir.']);
        }

        foreach ($data['lines'] as $index => $line) {
            if (bccomp($line['quantity'], '0', 3) <= 0) {
                throw ValidationException::withMessages([
                    "lines.{$index}.quantity" => 'Miktar pozitif olmalıdır.',
                ]);
            }

            $product = Product::query()->findOrFail($line['product_id']);

            if ($product->kind === ProductKind::Set) {
                throw ValidationException::withMessages([
                    "lines.{$index}.product_id" => 'Set ürün fiziksel ambar fişi satırı olamaz.',
                ]);
            }

            if ($data['direction'] === 'out' && $line['unit_cost'] !== null && $line['unit_cost'] !== '') {
                throw ValidationException::withMessages([
                    "lines.{$index}.unit_cost" => 'Çıkış fişinde birim maliyet girilemez.',
                ]);
            }

            if ($line['unit_cost'] !== null
                && $line['unit_cost'] !== ''
                && bccomp($line['unit_cost'], '0', 4) < 0) {
                throw ValidationException::withMessages([
                    "lines.{$index}.unit_cost" => 'Birim maliyet negatif olamaz.',
                ]);
            }
        }

        return DB::connection('period')->transaction(function () use ($data, $slip): WarehouseSlip {
            if ($slip) {
                $slip = WarehouseSlip::query()->lockForUpdate()->findOrFail($slip->id);

                if ($slip->status !== 'draft') {
                    throw new DomainException('Yalnız taslak ambar fişi düzenlenebilir.');
                }
            } else {
                $slip = new WarehouseSlip;
                $slip->status = 'draft';
                $slip->created_by = auth()->id();
            }

            $slip->location_id = $data['location_id'];
            $slip->slip_date = $data['slip_date'];
            $slip->direction = $data['direction'];
            $slip->reason = $data['reason'];
            $slip->note = $data['note'] ?? null;
            $slip->save();

            $slip->lines()->delete();

            foreach ($data['lines'] as $line) {
                $slip->lines()->create([
                    'product_id' => $line['product_id'],
                    'quantity' => bcadd($line['quantity'], '0', 3),
                    'unit_cost' => $line['unit_cost'] !== null && $line['unit_cost'] !== ''
                        ? bcadd($line['unit_cost'], '0', 4)
                        : null,
                    'note' => $line['note'] ?? null,
                ]);
            }

            return $slip->refresh()->load(['location', 'lines.product']);
        }, attempts: 3);
    }
}
