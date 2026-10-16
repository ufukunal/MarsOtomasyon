<?php

namespace App\Actions\Finance;

use App\Actions\Numbering\GenerateDocumentNumber;
use App\Actions\Periods\EnsurePeriodOpen;
use App\Models\Period\Contact;
use App\Models\Period\ContactTransaction;
use App\Models\Period\ContactTransfer;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

final class ReverseContactTransfer
{
    public function __construct(
        private readonly EnsurePeriodOpen $ensurePeriodOpen,
        private readonly GenerateDocumentNumber $numbers,
    ) {}

    public function handle(ContactTransfer $original, string $date, string $reason, string $key): ContactTransfer
    {
        MutationAuthorizer::authorize('contact_transfers.cancel');

        return IdempotencyKey::run($key, 'contact-transfer.reverse:'.$original->id, function () use ($original, $date, $reason): ContactTransfer {
            if (trim($reason) === '') {
                throw new DomainException('Cari virman ters kayıt gerekçesi zorunludur.');
            }
            $date = CarbonImmutable::parse($date)->startOfDay();
            $this->ensurePeriodOpen->handle($date);

            return DB::connection('period')->transaction(function () use ($original, $date, $reason): ContactTransfer {
                $locked = ContactTransfer::query()->lockForUpdate()->findOrFail($original->id);
                if ($locked->status !== 'posted' || $locked->reversal_of_id !== null
                    || ContactTransfer::query()->where('reversal_of_id', $locked->id)->exists()) {
                    throw new DomainException('Bu cari virman daha önce terslenmiş veya terslenemez.');
                }
                Contact::query()->whereIn('id', [$locked->source_contact_id, $locked->target_contact_id])
                    ->orderBy('id')->lockForUpdate()->get();
                $number = $this->numbers->handle('contact_transfer', $date->year);
                $actor = auth()->user();
                $originalEntries = ContactTransaction::query()
                    ->whereIn('id', [$locked->source_transaction_id, $locked->target_transaction_id])
                    ->get()->keyBy('id');
                $entries = [];
                foreach (['source', 'target'] as $side) {
                    $entry = $originalEntries->get($locked->{$side.'_transaction_id'});
                    if (! $entry) {
                        throw new DomainException('Virmanın muhasebe hareketleri eksik.');
                    }
                    $entries[$side] = ContactTransaction::query()->create([
                        'contact_id' => $entry->contact_id,
                        'transaction_type' => 'contact_transfer',
                        'direction' => $entry->direction === 'debit' ? 'credit' : 'debit',
                        'transaction_date' => $date->toDateString(),
                        'amount' => $entry->amount,
                        'currency' => 'TRY',
                        'reversal_of_id' => $entry->id,
                        'description' => $number.' · Ters kayıt: '.trim($reason),
                        'created_by' => $actor?->id,
                        'created_by_name' => $actor?->name,
                    ]);
                }
                $reversal = ContactTransfer::query()->create([
                    'number' => $number,
                    'source_contact_id' => $locked->source_contact_id,
                    'target_contact_id' => $locked->target_contact_id,
                    'source_transaction_id' => $entries['source']->id,
                    'target_transaction_id' => $entries['target']->id,
                    'balance_type' => $locked->balance_type,
                    'transfer_date' => $date->toDateString(),
                    'amount' => $locked->amount,
                    'currency' => 'TRY',
                    'reason' => trim($reason),
                    'status' => 'posted',
                    'reversal_of_id' => $locked->id,
                    'created_by' => $actor?->id,
                    'created_by_name' => $actor?->name,
                ]);
                AuditContext::period('Cari virman ters kaydı oluşturuldu.', [
                    'original_transfer_id' => $locked->id,
                    'reversal_transfer_id' => $reversal->id,
                ], $entries['source'], 'contact_transfer_reversed');

                return $reversal;
            }, attempts: 3);
        });
    }
}
