<?php

namespace App\Support\Documents;

use App\Enums\DocumentType;
use App\Models\Period\Document;
use App\Models\User;

/**
 * Centralized, permission-filtered navigation for all persisted ERP documents.
 * A link opens the real editor or correction workflow, never overwrites a
 * posted invoice or creates a fictitious draft.
 */
final class DocumentEditCatalog
{
    /**
     * Other real instruments are not records of the documents table.
     * Link to their existing state-aware editors/reversal screens.
     *
     * @return array<int,array{label:string,route:string,permission:string,note:string}>
     */
    public static function relatedModules(): array
    {
        return [
            ['label' => 'Stok Düzeltme Fişleri', 'route' => 'stock.warehouse-slips.index', 'permission' => 'warehouse_slips.view', 'note' => 'Taslak düzenlenir; kesinleşen stok fişi ters fişle düzeltilir.'],
            ['label' => 'Depo Transferleri', 'route' => 'stock.transfers.index', 'permission' => 'transfers.view', 'note' => 'Taslak düzenlenir; gönderim sonrası iptal veya düzeltme transferi gerekir.'],
            ['label' => 'Stok Sayımları', 'route' => 'stock.counts.index', 'permission' => 'stock_counts.view', 'note' => 'Taslak ve sayım kontrolü düzenlenir; stok kesinleşmesi geçmişi silinmez.'],
            ['label' => 'Ambar Fişleri', 'route' => 'warehouse.transport.index', 'permission' => 'warehouse_slips.view', 'note' => 'Taslak / hazır fiş düzenlenir; teslim geçmişi korunur.'],
            ['label' => 'Çekler', 'route' => 'finance.checks.index', 'permission' => 'securities.view', 'note' => 'Uygun çek düzenlenir; mali değişiklikte ters kayıt ve yeni çek gerekir.'],
            ['label' => 'Senetler', 'route' => 'finance.notes.index', 'permission' => 'securities.view', 'note' => 'Uygun senet düzenlenir; ciro edilmiş senetlerde bordro geçmişi korunur.'],
            ['label' => 'Cari Virman', 'route' => 'finance.contact-transfers', 'permission' => 'contact_transfers.view', 'note' => 'Cari virman terslenerek yeni hareketle düzeltilir.'],
            ['label' => 'Kasa / Banka İşlemleri', 'route' => 'finance.operations', 'permission' => 'finance_movements.view', 'note' => 'Mali hareketler ve finans virmanları ters kayıtla düzeltilir.'],
            ['label' => 'Üretim Emirleri', 'route' => 'production.orders', 'permission' => 'production_orders.view', 'note' => 'Üretim taslakları düzenlenir; tamamlanan üretim ters işlemle düzeltilir.'],
            ['label' => 'Fason İşlemleri', 'route' => 'production.subcontracting', 'permission' => 'subcontracting.view', 'note' => 'Sevk ve fason hizmet geçmişi korunarak mevcut işlemler kullanılır.'],
            ['label' => 'İthalat Dosyaları', 'route' => 'imports.shipments', 'permission' => 'import_shipments.view', 'note' => 'İthalat dosyası ve bağlı belge kayıtları mevcut iş akışından yönetilir.'],
        ];
    }

    /** @return array<string,array{label:string,route:string,permission:string,with_id:bool,mode:string}> */
    public static function entries(): array
    {
        return [
            DocumentType::Quote->value => ['label' => 'Satış Teklifi', 'route' => 'sales.quotes.edit', 'permission' => 'quotes.view', 'with_id' => true, 'mode' => 'document'],
            DocumentType::SalesOrder->value => ['label' => 'Satış Siparişi', 'route' => 'sales.orders.edit', 'permission' => 'sales_orders.view', 'with_id' => true, 'mode' => 'document'],
            DocumentType::Dispatch->value => ['label' => 'Satış İrsaliyesi', 'route' => 'sales.dispatches.edit', 'permission' => 'dispatches.view', 'with_id' => true, 'mode' => 'document'],
            DocumentType::SalesInvoice->value => ['label' => 'Satış Faturası', 'route' => 'sales.invoices.edit', 'permission' => 'sales_invoices.view', 'with_id' => true, 'mode' => 'document'],
            DocumentType::Proforma->value => ['label' => 'Proforma', 'route' => 'sales.proformas.show', 'permission' => 'proformas.view', 'with_id' => true, 'mode' => 'source'],
            DocumentType::Collection->value => ['label' => 'Tahsilat', 'route' => 'finance.collections.create', 'permission' => 'collections.view', 'with_id' => false, 'mode' => 'correction'],
            DocumentType::ContactDebitCredit->value => ['label' => 'Cari Borç / Alacak Fişi', 'route' => 'finance.contact-debit-credit', 'permission' => 'contacts.update', 'with_id' => false, 'mode' => 'correction'],
            DocumentType::PurchaseQuote->value => ['label' => 'Alış Teklifi', 'route' => 'purchases.quotes.edit', 'permission' => 'purchase_quotes.view', 'with_id' => true, 'mode' => 'document'],
            DocumentType::PurchaseOrder->value => ['label' => 'Satınalma Siparişi', 'route' => 'purchases.orders.edit', 'permission' => 'purchase_orders.view', 'with_id' => true, 'mode' => 'document'],
            DocumentType::PurchaseDispatch->value => ['label' => 'Alış İrsaliyesi', 'route' => 'purchases.dispatches.edit', 'permission' => 'purchase_dispatches.view', 'with_id' => true, 'mode' => 'document'],
            DocumentType::GoodsReceipt->value => ['label' => 'Mal Kabul', 'route' => 'purchases.receipts.edit', 'permission' => 'goods_receipts.view', 'with_id' => true, 'mode' => 'document'],
            DocumentType::SupplierInvoice->value => ['label' => 'Alış Faturası', 'route' => 'purchases.invoices.edit', 'permission' => 'supplier_invoices.view', 'with_id' => true, 'mode' => 'document'],
            DocumentType::Payment->value => ['label' => 'Tediye', 'route' => 'purchases.payments.create', 'permission' => 'payments.view', 'with_id' => false, 'mode' => 'correction'],
            DocumentType::Expense->value => ['label' => 'Gider Belgesi', 'route' => 'finance.operations', 'permission' => 'expenses.view', 'with_id' => false, 'mode' => 'correction'],
            DocumentType::Advance->value => ['label' => 'Avans', 'route' => 'finance.operations', 'permission' => 'advances.view', 'with_id' => false, 'mode' => 'correction'],
            DocumentType::AdvanceReturn->value => ['label' => 'Avans İadesi', 'route' => 'finance.operations', 'permission' => 'advances.view', 'with_id' => false, 'mode' => 'correction'],
            DocumentType::SalesReturn->value => ['label' => 'Satış İadesi', 'route' => 'returns.center', 'permission' => 'returns.view', 'with_id' => false, 'mode' => 'return'],
            DocumentType::PurchaseReturn->value => ['label' => 'Alış İadesi', 'route' => 'returns.center', 'permission' => 'returns.view', 'with_id' => false, 'mode' => 'return'],
        ];
    }

    /** @return array<string,array{label:string,route:string,permission:string,with_id:bool,mode:string}> */
    public static function permitted(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return array_filter(
            self::entries(),
            static fn (array $entry): bool => $user->can($entry['permission']),
        );
    }

    public static function canCorrect(?User $user, Document $document): bool
    {
        if ($user === null || self::hasReversal($document)) {
            return false;
        }

        if (in_array($document->document_type, [
            DocumentType::Collection, DocumentType::Payment,
            DocumentType::ContactDebitCredit, DocumentType::Expense,
            DocumentType::Advance, DocumentType::AdvanceReturn,
        ], true) && $document->status !== 'posted') {
            return false;
        }

        if (in_array($document->document_type, [
            DocumentType::SalesReturn, DocumentType::PurchaseReturn,
        ], true) && ! in_array($document->status, ['draft', 'posted'], true)) {
            return false;
        }

        $needed = match ($document->document_type) {
            DocumentType::Collection => ['collections.cancel', 'collections.create'],
            DocumentType::Payment => ['payments.cancel', 'payments.create'],
            DocumentType::ContactDebitCredit => ['contacts.update'],
            DocumentType::Expense => ['expenses.cancel', 'expenses.create'],
            DocumentType::Advance, DocumentType::AdvanceReturn => ['advances.cancel', 'advances.create'],
            DocumentType::SalesReturn, DocumentType::PurchaseReturn =>
                $document->status === 'draft' ? ['returns.update'] : ['returns.cancel', 'returns.create'],
            default => [],
        };

        foreach ($needed as $permission) {
            if (! $user->can($permission)) {
                return false;
            }
        }

        return true;
    }

    public static function hasReversal(Document $document): bool
    {
        return $document->incomingRelations->contains(
            fn ($relation): bool => $relation->relation_type === 'reversal_of'
        ) || $document->outgoingRelations->contains(
            fn ($relation): bool => $relation->relation_type === 'reversal_of'
        );
    }

    /** @param array{label:string,route:string,permission:string,with_id:bool,mode:string} $entry */
    public static function url(Document $document, array $entry): string
    {
        if ($entry['with_id']) {
            return route($entry['route'], ['id' => $document->id]);
        }

        if (self::hasReversal($document) || ! self::canCorrect(
            auth()->user() instanceof User ? auth()->user() : null,
            $document,
        )) {
            // Keep the exact record accessible without selecting a protected
            // reversal mutation or losing its identity in a module-wide list.
            return route('documents.history', ['id' => $document->id]);
        }

        return route($entry['route'], ['belge' => $document->id]);
    }

    /** @param array{label:string,route:string,permission:string,with_id:bool,mode:string} $entry */
    public static function actionLabel(Document $document, array $entry): string
    {
        if (self::hasReversal($document)) {
            return 'Geçmişi İncele';
        }

        if (! $entry['with_id'] && ! self::canCorrect(
            auth()->user() instanceof User ? auth()->user() : null,
            $document,
        )) {
            return 'İncele';
        }

        if ($entry['mode'] === 'document') {
            $user = auth()->user();
            $permission = str_replace('.view', '', $entry['permission']);

            if (! $user instanceof User || ! $user->can($permission.'.update')) {
                return 'İncele';
            }

            if ($document->status === 'posted' && ! $user->can($permission.'.cancel')) {
                return 'İncele';
            }

            if ($document->status === 'posted'
                && in_array($document->document_type, [DocumentType::SalesInvoice, DocumentType::SupplierInvoice], true)
                && ! $user->can($permission.'.create')) {
                return 'İncele';
            }
        }

        if ($entry['mode'] === 'source') {
            return 'Kaynak / Proforma Görünümü';
        }

        if ($document->status === 'draft') {
            return 'Düzenle';
        }

        if (in_array($document->document_type, [DocumentType::SalesInvoice, DocumentType::SupplierInvoice], true)) {
            return 'Düzenle';
        }

        return $entry['mode'] === 'document' ? 'Düzenle / Düzelt' : 'Düzeltmeye Git';
    }
}
