<?php

namespace App\Actions\Documents;

use App\Actions\Purchases\PostSupplierInvoice;
use App\Actions\Sales\PostSalesInvoice;
use App\Enums\DocumentType;
use App\Models\Period\BankMovement;
use App\Models\Period\CashMovement;
use App\Models\Period\ContactTransaction;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Models\Period\DocumentRelation;
use App\Models\Period\StockMovement;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use App\Support\Period\PeriodContext;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * User-facing invoice edit/delete commands. Reversal remains an internal,
 * audited accounting operation; it never physically destroys a posted invoice.
 */
final class ManageInvoiceRecord
{
    public function __construct(
        private readonly ReverseDocument $reverse,
        private readonly CreateEditableDocumentCopy $editableCopy,
        private readonly PostSalesInvoice $postSales,
        private readonly PostSupplierInvoice $postPurchase,
    ) {}

    private function permission(Document $document): string
    {
        return match ($document->document_type) {
            DocumentType::SalesInvoice => 'sales_invoices',
            DocumentType::SupplierInvoice => 'supplier_invoices',
            default => throw new DomainException('Bu işlem yalnız satış/alış faturalarında kullanılabilir.'),
        };
    }

    /**
     * Opening the editor never reverses an existing invoice. The original
     * remains financially active until the edited draft is saved successfully.
     */
    public function edit(Document $invoice, string $key): Document
    {
        $permission = $this->permission($invoice);
        MutationAuthorizer::authorize($permission.'.view');
        MutationAuthorizer::authorize($permission.'.update');
        PeriodContext::ensureWritable();

        return DB::connection('period')->transaction(function () use ($invoice, $key, $permission): Document {
            $locked = Document::query()->lockForUpdate()->findOrFail($invoice->id);
            if ($locked->document_type !== $invoice->document_type) {
                throw new DomainException('Fatura türü değişmiş.');
            }
            if ($locked->status === 'draft') {
                return $locked;
            }
            if ($locked->status !== 'posted') {
                throw new DomainException('Bu fatura mevcut durumunda düzenlenemiyor.');
            }

            MutationAuthorizer::authorize($permission.'.create');
            MutationAuthorizer::authorize($permission.'.cancel');
            $reversal = DocumentRelation::query()
                ->where('relation_type', 'reversal_of')
                ->where('target_document_id', $locked->id)
                ->with('sourceDocument')
                ->first();
            if ($reversal !== null && str_starts_with(
                (string) ($reversal->sourceDocument?->notes ?? ''),
                'Fatura kullanıcı talebiyle silindi;'
            )) {
                throw new DomainException('Silinmiş fatura tekrar düzenlenemez; geçmişinden incelenebilir.');
            }

            return $this->editableCopy->handle(
                $locked,
                hash('sha256', $key.':editable-copy'),
                allowPostedInvoiceEdit: true,
            );
        }, attempts: 3);
    }

    /**
     * The edited draft is posted only after the original invoice's reversal.
     * Both monetary mutations share one period transaction. If posting fails,
     * the original stays financially effective and the draft stays editable.
     */
    public function save(Document $draft, string $key, bool $riskAccepted = false, bool $deviationAccepted = false): Document
    {
        $permission = $this->permission($draft);
        MutationAuthorizer::authorize($permission.'.view');
        MutationAuthorizer::authorize($permission.'.create');
        PeriodContext::ensureWritable();

        return DB::connection('period')->transaction(function () use ($draft, $key, $riskAccepted, $deviationAccepted, $permission): Document {
            $locked = Document::query()->lockForUpdate()->findOrFail($draft->id);
            if ($locked->document_type !== $draft->document_type || $locked->status !== 'draft') {
                throw new DomainException('Yalnız açık fatura taslağı kaydedilebilir.');
            }

            $link = DocumentRelation::query()
                ->where('relation_type', 'edit_copy_of')
                ->where('target_document_id', $locked->id)
                ->first();
            if ($link !== null) {
                MutationAuthorizer::authorize($permission.'.update');
                $source = Document::query()->lockForUpdate()->findOrFail($link->source_document_id);
                if ($source->document_type !== $locked->document_type || $source->status !== 'posted') {
                    throw new DomainException('Düzenleme kaynağı artık geçerli değil.');
                }
                $reversal = DocumentRelation::query()
                    ->where('relation_type', 'reversal_of')
                    ->where('target_document_id', $source->id)
                    ->with('sourceDocument')
                    ->first();
                if ($reversal !== null && str_starts_with(
                    (string) ($reversal->sourceDocument?->notes ?? ''),
                    'Fatura kullanıcı talebiyle silindi;'
                )) {
                    throw new DomainException('Kaynak fatura silinmiş, taslak işlenemez.');
                }
                if ($reversal === null) {
                    MutationAuthorizer::authorize($permission.'.cancel');
                    $this->reverse->handle(
                        $source,
                        now()->toDateString(),
                        'Fatura düzenlemesi: önceki kayıt güvenle geri alındı.',
                        hash('sha256', $key.':revision-reverse'),
                    );
                }
            }

            return match ($locked->document_type) {
                DocumentType::SalesInvoice => $this->postSales->handle(
                    $locked, hash('sha256', $key.':post'), $riskAccepted,
                ),
                DocumentType::SupplierInvoice => $this->postPurchase->handle(
                    $locked, hash('sha256', $key.':post'), $deviationAccepted,
                ),
                default => throw new DomainException('Fatura türü kaydedilemiyor.'),
            };
        }, attempts: 3);
    }

    /**
     * A draft without accounting/stock dependents is physically deleted.
     * A posted invoice is reversed and disappears only from the ordinary
     * invoice list; its original and reversal stay in audit/history.
     */
    public function delete(Document $invoice, string $key): void
    {
        $permission = $this->permission($invoice);
        MutationAuthorizer::authorize($permission.'.view');
        MutationAuthorizer::authorize($permission.'.cancel');
        PeriodContext::ensureWritable();

        $current = Document::query()->findOrFail($invoice->id);
        if ($current->document_type !== $invoice->document_type) {
            throw new DomainException('Fatura türü değişmiş.');
        }

        if ($current->status === 'posted') {
            $this->reverse->handle(
                $current,
                now()->toDateString(),
                'Fatura kullanıcı talebiyle silindi; mali kayıt ters hareketle korundu.',
                hash('sha256', $key.':delete-reversal'),
            );

            return;
        }

        if ($current->status !== 'draft') {
            throw new DomainException('Bu faturanın durumu silme işlemine uygun değil.');
        }

        IdempotencyKey::run($key, 'invoice.delete:'.$current->id, function () use ($current): array {
            $locked = Document::query()->with('lines')->lockForUpdate()->findOrFail($current->id);
            if ($locked->status !== 'draft' || $locked->document_type !== $current->document_type) {
                throw new DomainException('Fatura artık silinebilir taslak durumunda değil.');
            }

            $lineIds = $locked->lines->pluck('id');
            if (DocumentRelation::query()->where('source_document_id', $locked->id)->exists()
                || ($lineIds->isNotEmpty() && DocumentLine::query()->whereIn('source_line_id', $lineIds)->exists())
                || ContactTransaction::query()->where('document_id', $locked->id)->exists()
                || StockMovement::query()->where('document_id', $locked->id)
                    ->where('document_type', $locked->document_type->value)->exists()
                || BankMovement::query()->where('document_id', $locked->id)->exists()
                || CashMovement::query()->where('document_id', $locked->id)->exists()) {
                throw new DomainException('Bağlı mali veya stok işlemi bulunan fatura doğrudan silinemez.');
            }

            AuditContext::period('Taslak fatura silindi.', [
                'deleted_document_id' => $locked->id,
                'document_type' => $locked->document_type->value,
                'number' => $locked->number,
                'status' => 'draft',
            ], null, 'invoice_draft_deleted');

            DocumentRelation::query()->where('target_document_id', $locked->id)->delete();
            foreach ($locked->lines as $line) {
                $line->delete();
            }
            $locked->delete();

            return ['deleted_document_id' => $current->id];
        });
    }
}
