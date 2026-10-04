<?php

namespace App\Actions\Stock;

use App\Enums\ProductKind;
use App\Models\Period\Location;
use App\Models\Period\Product;
use App\Models\Period\Transfer;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Period\PeriodContext;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SaveTransferDraft
{
    /**
     * @param array{
     *   from_location_id:int,
     *   to_location_id:int,
     *   transfer_date:string,
     *   note?:?string,
     *   lines:list<array{product_id:int,quantity:string}>
     * } $data
     */
    public function handle(array $data, ?Transfer $transfer = null): Transfer
    {
        MutationAuthorizer::authorize($transfer ? 'transfers.update' : 'transfers.create');
        PeriodContext::ensureWritable();

        if ($data['from_location_id'] === $data['to_location_id']) {
            throw ValidationException::withMessages([
                'to_location_id' => 'Kaynak ve hedef lokasyon aynı olamaz.',
            ]);
        }

        Location::query()->findOrFail($data['from_location_id']);
        Location::query()->findOrFail($data['to_location_id']);
        CarbonImmutable::parse($data['transfer_date']);

        if ($data['lines'] === []) {
            throw ValidationException::withMessages([
                'lines' => 'Transfer en az bir ürün satırı içermelidir.',
            ]);
        }

        foreach ($data['lines'] as $index => $line) {
            if (bccomp($line['quantity'], '0', 3) <= 0) {
                throw ValidationException::withMessages([
                    "lines.{$index}.quantity" => 'Transfer miktarı pozitif olmalıdır.',
                ]);
            }

            $product = Product::query()->findOrFail($line['product_id']);

            if ($product->kind === ProductKind::Set) {
                throw ValidationException::withMessages([
                    "lines.{$index}.product_id" => 'Set ürün fiziksel transfer satırı olamaz.',
                ]);
            }
        }

        return DB::connection('period')->transaction(function () use ($data, $transfer): Transfer {
            if ($transfer) {
                $transfer = Transfer::query()->lockForUpdate()->findOrFail($transfer->id);

                if ($transfer->status !== 'draft') {
                    throw new DomainException('Yalnız taslak transfer düzenlenebilir.');
                }
            } else {
                $transfer = new Transfer;
                $transfer->status = 'draft';
                $transfer->created_by = auth()->id();
            }

            $transfer->from_location_id = $data['from_location_id'];
            $transfer->to_location_id = $data['to_location_id'];
            $transfer->transfer_date = $data['transfer_date'];
            $transfer->note = $data['note'] ?? null;
            $transfer->save();

            $transfer->lines()->delete();

            foreach ($data['lines'] as $line) {
                $transfer->lines()->create([
                    'product_id' => $line['product_id'],
                    'quantity' => bcadd($line['quantity'], '0', 3),
                    'received_quantity' => '0.000',
                ]);
            }

            return $transfer->refresh()->load(['fromLocation', 'toLocation', 'lines.product']);
        }, attempts: 3);
    }
}
