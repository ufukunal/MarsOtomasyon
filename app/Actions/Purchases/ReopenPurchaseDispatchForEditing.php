<?php

namespace App\Actions\Purchases;

use App\Actions\Periods\EnsurePeriodOpen;
use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\DocumentRelation;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use App\Support\Period\PeriodContext;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * A supplier dispatch has no stock/ledger effect until Goods Receipt.
 * It may be safely reopened only before any downstream document is created.
 */
final class ReopenPurchaseDispatchForEditing
{
    public function __construct(private readonly EnsurePeriodOpen $period) {}

    public function eligible(Document $dispatch): bool
    {
        return $dispatch->document_type === DocumentType::PurchaseDispatch
            && $dispatch->status === 'confirmed'
            && ! DocumentRelation::query()
                ->where('source_document_id', $dispatch->id)
                ->where('relation_type', '!=', 'edit_copy_of')
                ->exists()
            && ! DocumentLine::query()
                ->whereIn('source_line_id', $dispatch->lines()->select('id'))
                ->exists();
    }

    public function handle(Document $dispatch, string $key): Document
    {
        MutationAuthorizer::authorize('purchase_dispatches.view');
        MutationAuthorizer::authorize('purchase_dispatches.update');
        PeriodContext::ensureWritable();

        return IdempotencyKey::run(
            $key,
            'purchase-dispatch.reopen:'.$dispatch->id,
            function () use ($dispatch): Document {
                return DB::connection('period')->transaction(function () use ($dispatch): Document {
                    $locked = Document::query()->lockForUpdate()->findOrFail($dispatch->id);

                    if (! $this->eligible($locked)) {
                        throw new DomainException(
                            'Bağlı Mal Kabul veya diğer belge bulunan alış irsaliyesi düzenlemeye açılamaz.'
                        );
                    }

                    $this->period->handle(CarbonImmutable::parse($locked->document_date));
                    $locked->status = 'draft';
                    $locked->version = (int) $locked->version + 1;
                    $locked->save();

                    AuditContext::period(
                        'Alış irsaliyesi kontrollü olarak yeniden düzenlemeye açıldı.',
                        ['document_id' => $locked->id, 'number' => $locked->number],
                        $locked,
                        'purchase_dispatch_reopened',
                    );

                    return $locked->load('lines');
                }, attempts: 3);
            },
        );
    }
}
