<?php

namespace App\Actions\Documents;

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
    ) {}

    private function permission(Document $document): string
    {
        return match ($document->document_type) {
            DocumentType::SalesInvoice => 'sales_invoices',
            DocumentType::SupplierInvoice => 'supplier_invoices',
            default => throw new DomainException('Bu işlem yalnız satış/alış faturalarında kullanılabilir.'),
        };
    }

    public function edit(Document $invoice, string $key): Document
    {
        $permission = $this->permission($invoice);
        foreach (['view', 'update', 'create'] as $operation) {
            MutationAuthorizer::authorize($permission.'.'.$operation);
        }
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

            // A posted document is immutable: hide this accounting correction
            // from the UI, not from the ledger or audit log.
            if (! DocumentRelation::query()
                ->where('relation_type', 'reversal_of')
                ->where('target_document_id', $locked->id)
                ->exists()) {
                MutationAuthorizer::authorize($permission.'.cancel');
                $this->reverse->handle(
                    $locked,
                    now()->toDateString(),
                    'Fatura düzenlemesi için önceki kayıt geri alındı.',
                    hash('sha256', $key.':reverse'),
                );
            }

            return $this->editableCopy->handle(
                $locked,
                hash('sha256', $key.':editable-copy'),
            );
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
