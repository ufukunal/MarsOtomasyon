<?php

namespace App\Actions\Documents;

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
 * Creates an independent, editable draft of a locked document.
 *
 * A finalized ledger document cannot be silently reopened. The invoice
 * editing path may create a non-posted independent copy while retaining the
 * original invoice's financial effect until the new invoice posts atomically
 * with an audited reversal. All other posted documents still require a
 * preceding reversal. Opening a draft never alters the source's ledger.
 * A purchase dispatch is reopened in place only when no Goods Receipt exists,
 * because its draft lines already reserve quantities from the source order.
 */
final class CreateEditableDocumentCopy
{
    public function __construct(private readonly EnsurePeriodOpen $period) {}

    private const PERMISSIONS = [
        DocumentType::Quote->value => 'quotes',
        DocumentType::SalesOrder->value => 'sales_orders',
        DocumentType::Dispatch->value => 'dispatches',
        DocumentType::SalesInvoice->value => 'sales_invoices',
        DocumentType::PurchaseQuote->value => 'purchase_quotes',
        DocumentType::PurchaseOrder->value => 'purchase_orders',

        DocumentType::SupplierInvoice->value => 'supplier_invoices',
    ];

    public function handle(Document $original, string $key, bool $allowPostedInvoiceEdit = false): Document
    {
        $type = $original->document_type->value;
        $permission = self::PERMISSIONS[$type] ?? null;

        if ($permission === null) {
            throw new DomainException('Bu belge türü için düzenleme kopyası desteklenmiyor.');
        }

        MutationAuthorizer::authorize($permission.'.view');
        MutationAuthorizer::authorize($permission.'.update');
        MutationAuthorizer::authorize($permission.'.create');
        PeriodContext::ensureWritable();

        return IdempotencyKey::run(
            $key,
            'document.edit-copy:'.$original->id,
            function () use ($original, $type, $allowPostedInvoiceEdit): Document {
                return DB::connection('period')->transaction(function () use ($original, $type, $allowPostedInvoiceEdit): Document {
                    $locked = Document::query()
                        ->with(['lines', 'incomingRelations'])
                        ->lockForUpdate()
                        ->findOrFail($original->id);

                    if ($locked->document_type->value !== $type || $locked->status === 'draft') {
                        throw new DomainException('Taslak belge zaten doğrudan düzenlenebilir.');
                    }

                    if ($type === DocumentType::Quote->value && $locked->number !== null) {
                        throw new DomainException('Numaralı satış teklifi için Revizyon Oluştur kullanılmalıdır.');
                    }

                    // Do not allow corrections of the reversal itself.
                    if (DocumentRelation::query()
                        ->where('relation_type', 'reversal_of')
                        ->where('source_document_id', $locked->id)
                        ->exists()) {
                        throw new DomainException('Ters kayıt fişi yeni düzenleme kopyasının kaynağı olamaz.');
                    }

                    if ($allowPostedInvoiceEdit && ! in_array($locked->document_type, [
                        DocumentType::SalesInvoice, DocumentType::SupplierInvoice,
                    ], true)) {
                        throw new DomainException('Önceden tersleme yapılmadan düzenleme yalnız faturalar için mümkündür.');
                    }

                    $reversal = DocumentRelation::query()
                        ->where('relation_type', 'reversal_of')
                        ->where('target_document_id', $locked->id)
                        ->with('sourceDocument')
                        ->first();
                    if (in_array($locked->document_type, [DocumentType::SalesInvoice, DocumentType::SupplierInvoice], true)
                        && $reversal !== null && str_starts_with(
                            (string) ($reversal->sourceDocument?->notes ?? ''),
                            'Fatura kullanıcı talebiyle silindi;'
                        )) {
                        throw new DomainException('Silinmiş fatura yeniden düzenlenemez.');
                    }

                    if ($locked->status === 'posted' && ! $allowPostedInvoiceEdit && $reversal === null) {
                        throw new DomainException('Kesinleşmiş belge önce Ters Kayıt ile düzeltilmelidir.');
                    }

                    // A double click or another tab must not create duplicate drafts.
                    $existing = DocumentRelation::query()
                        ->where('source_document_id', $locked->id)
                        ->where('relation_type', 'edit_copy_of')
                        ->whereHas('targetDocument', fn ($query) => $query->where('status', 'draft'))
                        ->with('targetDocument.lines')
                        ->orderByDesc('id')
                        ->first();

                    if ($existing?->targetDocument !== null) {
                        return $existing->targetDocument;
                    }

                    $today = CarbonImmutable::now();
                    $this->period->handle($today);
                    $actor = auth()->user();

                    $copy = Document::query()->create([
                        ...$locked->only([
                            'due_date', 'valid_until', 'contact_id', 'currency',
                            'exchange_rate', 'discount_rate', 'discount_amount',
                            'additional_discount_amount', 'subtotal', 'tax_base',
                            'vat_amount', 'rounding_difference', 'grand_total',
                            'requirements_snapshot', 'ui_meta', 'notes',
                        ]),
                        'document_type' => $type,
                        'number' => null,
                        'revision_no' => $type === DocumentType::Quote->value ? 1 : 0,
                        'document_date' => $today->toDateString(),
                        'status' => 'draft',
                        'created_by' => $actor?->id,
                        'created_by_name' => $actor?->name,
                    ]);

                    foreach ($locked->lines as $line) {
                        DocumentLine::query()->create([
                            ...$line->only([
                                'line_no', 'line_kind', 'product_id', 'description',
                                'unit_id', 'quantity', 'conversion_factor',
                                'base_quantity', 'location_id', 'unit_price',
                                'line_discount_rate', 'line_discount_amount',
                                'vat_rate', 'line_total', 'configuration',
                                'source_line_id',
                            ]),
                            'document_id' => $copy->id,
                            'reserve_stock' => false,
                            'cancelled_quantity' => '0.000',
                        ]);
                    }

                    // Preserve genuine source-document lineage for invoices and
                    // dispatches, not reversals or other edit-copy audit links.
                    foreach ($locked->incomingRelations as $relation) {
                        if (in_array($relation->relation_type, [
                            'revision_of', 'reversal_of', 'edit_copy_of',
                        ], true)) {
                            continue;
                        }

                        DocumentRelation::query()->create([
                            'source_document_id' => $relation->source_document_id,
                            'target_document_id' => $copy->id,
                            'relation_type' => $relation->relation_type,
                            'created_by' => $actor?->id,
                            'created_by_name' => $actor?->name,
                        ]);
                    }

                    DocumentRelation::query()->create([
                        'source_document_id' => $locked->id,
                        'target_document_id' => $copy->id,
                        'relation_type' => 'edit_copy_of',
                        'created_by' => $actor?->id,
                        'created_by_name' => $actor?->name,
                    ]);

                    AuditContext::period(
                        'Belgenin yeni düzenleme taslağı oluşturuldu.',
                        [
                            'original_document_id' => $locked->id,
                            'edit_copy_document_id' => $copy->id,
                            'original_status' => $locked->status,
                            'new_document_is_independent' => true,
                        ],
                        $copy,
                        'document_edit_copy_created',
                    );

                    return $copy->load('lines');
                }, attempts: 3);
            },
        );
    }
}
