<?php

namespace App\Actions\Documents;

use App\Models\Period\BankMovement;
use App\Models\Period\CashMovement;
use App\Models\Period\ContactTransaction;
use App\Models\Period\Document;
use DomainException;
use Illuminate\Support\Facades\DB;

final class VerifyReversal
{
    public function handle(Document $original, Document $reversal): void
    {
        if ($reversal->document_type !== $original->document_type
            || bccomp((string) $reversal->grand_total, (string) $original->grand_total, 4) !== 0) {
            throw new DomainException('Ters belge başlık doğrulaması başarısız.');
        }

        $relation = DB::connection('period')->table('document_relations')
            ->where('relation_type', 'reversal_of')
            ->where('source_document_id', $reversal->id)
            ->where('target_document_id', $original->id)
            ->exists();

        if (! $relation) {
            throw new DomainException('Ters belge ilişkisi bulunamadı.');
        }

        $originalContact = ContactTransaction::query()->where('document_id', $original->id)->first();

        if ($originalContact) {
            $reverseContact = ContactTransaction::query()->where('document_id', $reversal->id)->first();

            if (! $reverseContact
                || $reverseContact->reversal_of_id !== $originalContact->id
                || $reverseContact->direction === $originalContact->direction
                || bccomp((string) $reverseContact->amount, (string) $originalContact->amount, 4) !== 0) {
                throw new DomainException('Ters cari hareket doğrulaması başarısız.');
            }
        }

        $originalCash = CashMovement::query()->where('document_id', $original->id)->first();

        if ($originalCash) {
            $reverseCash = CashMovement::query()->where('document_id', $reversal->id)->first();

            if (! $reverseCash
                || $reverseCash->cash_account_id !== $originalCash->cash_account_id
                || $reverseCash->direction === $originalCash->direction
                || bccomp((string) $reverseCash->amount, (string) $originalCash->amount, 4) !== 0) {
                throw new DomainException('Ters kasa hareket doğrulaması başarısız.');
            }
        }

        $originalBank = BankMovement::query()->where('document_id', $original->id)->first();

        if ($originalBank) {
            $reverseBank = BankMovement::query()->where('document_id', $reversal->id)->first();

            if (! $reverseBank
                || $reverseBank->bank_account_id !== $originalBank->bank_account_id
                || $reverseBank->direction === $originalBank->direction
                || bccomp((string) $reverseBank->amount, (string) $originalBank->amount, 4) !== 0) {
                throw new DomainException('Ters banka hareket doğrulaması başarısız.');
            }
        }

        $originalStock = DB::connection('period')->table('stock_movements')
            ->where('document_type', $original->document_type->value)
            ->where('document_id', $original->id)
            ->selectRaw("product_id, location_id, direction, SUM(quantity)::text AS quantity")
            ->groupBy('product_id', 'location_id', 'direction')
            ->get();

        foreach ($originalStock as $row) {
            $expectedDirection = $row->direction === 'out' ? 'in' : 'out';
            $reverseQuantity = DB::connection('period')->table('stock_movements')
                ->where('document_type', $reversal->document_type->value)
                ->where('document_id', $reversal->id)
                ->where('product_id', $row->product_id)
                ->where('location_id', $row->location_id)
                ->where('direction', $expectedDirection)
                ->selectRaw('COALESCE(SUM(quantity), 0)::text AS quantity')
                ->value('quantity');

            if (bccomp((string) $reverseQuantity, (string) $row->quantity, 3) !== 0) {
                throw new DomainException('Ters stok hareketi doğrulaması başarısız.');
            }
        }
    }
}
