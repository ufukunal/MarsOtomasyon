<?php

namespace App\Actions\Imports;

use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Period\PeriodContext;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Manually verified reporting provenance; never alters actual stock, costs or invoices.
 * The operator must confirm the source container using warehouse evidence.
 */
final class SaveImportReportingAllocation
{
    /** @param array{source_type:string,source_id:int,container_id:int,quantity:string,note?:string} $input */
    public function handle(array $input): int
    {
        MutationAuthorizer::authorize('import_shipments.update');
        PeriodContext::ensureWritable();

        $type = (string) ($input['source_type'] ?? '');
        $sourceId = (int) ($input['source_id'] ?? 0);
        $containerId = (int) ($input['container_id'] ?? 0);
        $quantity = trim((string) ($input['quantity'] ?? ''));
        $note = trim((string) ($input['note'] ?? ''));

        if (! in_array($type, ['sales', 'return', 'fire', 'scrap'], true)
            || $sourceId <= 0 || $containerId <= 0
            || ! preg_match('/^\d+(?:\.\d{1,3})?$/D', $quantity)
            || bccomp($quantity, '0', 3) <= 0
            || mb_strlen($note) > 2000) {
            throw new DomainException('Kaynak türü, ID, konteyner ve pozitif miktar geçerli olmalıdır.');
        }

        return DB::connection('period')->transaction(function () use ($type, $sourceId, $containerId, $quantity, $note): int {
            $db = DB::connection('period');
            // All source allocations in the same container serialize here.
            $container = $db->table('containers as ct')
                ->join('import_files as f', 'f.id', '=', 'ct.import_file_id')
                ->where('ct.id', $containerId)
                ->lockForUpdate()
                ->first(['ct.id', 'ct.import_file_id', 'f.status', 'f.received_at']);

            if (! $container || ! in_array((string) $container->status, ['received', 'closed'], true)) {
                throw new DomainException('Yalnız teslim alınmış ithalatın konteynerine eşleştirme yapılabilir.');
            }

            $source = $this->source($type, $sourceId);
            if ($source === null) {
                throw new DomainException('Kesinleşmiş ve aktif kaynak hareketi bulunamadı.');
            }

            if ($container->received_at === null || $source['date'] < (string) $container->received_at) {
                throw new DomainException('Kaynak hareketi ithalat tesliminden önce olamaz.');
            }

            $productId = $source['product_id'];
            $imported = (string) ($db->table('packages')
                ->where('import_file_id', $container->import_file_id)
                ->where('container_id', $containerId)
                ->where('product_id', $productId)
                ->sum('quantity') ?: '0');

            if (bccomp($imported, '0', 3) <= 0) {
                throw new DomainException('İlgili ürün bu konteynerde bulunmuyor.');
            }

            $already = (string) ($db->table('import_reporting_allocations')
                ->where('source_type', $type)
                ->where('source_id', $sourceId)
                ->whereNull('voided_at')
                ->sum('quantity') ?: '0');

            if (bccomp(bcadd($already, $quantity, 3), $source['quantity'], 3) > 0) {
                throw new DomainException('Aynı kaynak hareketinin eşleştirme toplamı miktarı aşamaz.');
            }

            // A returned quantity belongs to an invoiced source line from the
            // SAME container; this prevents unsupported negative net sales.
            if ($type === 'return') {
                $relatedSales = (string) ($db->table('import_reporting_allocations')
                    ->where('source_type', 'sales')
                    ->where('source_id', $source['source_sales_line_id'])
                    ->where('container_id', $containerId)
                    ->whereNull('voided_at')
                    ->sum('quantity') ?: '0');
                $otherReturns = (string) ($db->table('import_reporting_allocations as a')
                    ->join('document_lines as l', 'l.id', '=', 'a.source_id')
                    ->where('a.source_type', 'return')
                    ->where('a.container_id', $containerId)
                    ->where('l.source_line_id', $source['source_sales_line_id'])
                    ->whereNull('a.voided_at')
                    ->sum('a.quantity') ?: '0');

                if (bccomp(bcadd($otherReturns, $quantity, 3), $relatedSales, 3) > 0) {
                    throw new DomainException('İade eşleştirmesi aynı konteynerde eşleştirilmiş satıştan fazla olamaz.');
                }
            }

            $occupied = $db->table('import_reporting_allocations')
                ->where('container_id', $containerId)
                ->where('product_id', $productId)
                ->whereNull('voided_at')
                ->selectRaw(
                    "COALESCE(SUM(CASE WHEN source_type = 'return' THEN -quantity ELSE quantity END), 0)::text AS used"
                )->value('used');

            $signed = $type === 'return' ? bcmul($quantity, '-1', 3) : $quantity;
            if (bccomp(bcadd((string) $occupied, $signed, 3), $imported, 3) > 0) {
                throw new DomainException('Konteynerdeki ithal miktarı eşleştirilen net çıkışları karşılamıyor.');
            }

            $existing = $db->table('import_reporting_allocations')
                ->where('source_type', $type)
                ->where('source_id', $sourceId)
                ->where('container_id', $containerId)
                ->whereNull('voided_at')->exists();
            if ($existing) {
                throw new DomainException('Bu kaynak/konteyner eşleştirmesi mevcut; önce iptal edin.');
            }

            $id = (int) $db->table('import_reporting_allocations')->insertGetId([
                'import_file_id' => $container->import_file_id,
                'container_id' => $containerId,
                'product_id' => $productId,
                'source_type' => $type,
                'source_id' => $sourceId,
                'event_date' => $source['date'],
                'quantity' => bcadd($quantity, '0', 3),
                'note' => $note !== '' ? $note : null,
                'created_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            AuditContext::period('İthalat kaynak eşleştirmesi oluşturuldu.', [
                'allocation_id' => $id, 'import_file_id' => $container->import_file_id,
                'container_id' => $containerId, 'source_type' => $type,
                'source_id' => $sourceId, 'quantity' => $quantity,
            ], null, 'import_reporting_allocation_created');

            return $id;
        }, attempts: 3);
    }

    public function void(int $id, string $reason): void
    {
        MutationAuthorizer::authorize('import_shipments.update');
        PeriodContext::ensureWritable();
        $reason = trim($reason);

        if ($id <= 0 || $reason === '' || mb_strlen($reason) > 2000) {
            throw new DomainException('Eşleştirme ve iptal gerekçesi zorunlu.');
        }

        DB::connection('period')->transaction(function () use ($id, $reason): void {
            $db = DB::connection('period');
            $allocation = $db->table('import_reporting_allocations')->where('id', $id)
                ->lockForUpdate()->first();

            if (! $allocation || $allocation->voided_at !== null) {
                throw new DomainException('Aktif eşleştirme bulunamadı.');
            }

            // Prevent retracting a sale source while an active linked return remains.
            if ($allocation->source_type === 'sales') {
                $returnExists = $db->table('import_reporting_allocations as a')
                    ->join('document_lines as l', 'l.id', '=', 'a.source_id')
                    ->where('a.source_type', 'return')
                    ->where('a.container_id', $allocation->container_id)
                    ->where('l.source_line_id', $allocation->source_id)
                    ->whereNull('a.voided_at')->exists();
                if ($returnExists) {
                    throw new DomainException('Önce satışa bağlı iade eşleştirmesini iptal edin.');
                }
            }

            $db->table('import_reporting_allocations')->where('id', $id)->update([
                'voided_at' => now(), 'voided_by' => auth()->id(),
                'void_reason' => $reason, 'updated_at' => now(),
            ]);

            AuditContext::period('İthalat kaynak eşleştirmesi iptal edildi.', [
                'allocation_id' => $id, 'reason' => $reason,
            ], null, 'import_reporting_allocation_voided');
        }, attempts: 3);
    }

    /** @return array{product_id:int,quantity:string,date:string,source_sales_line_id?:int}|null */
    private function source(string $type, int $sourceId): ?array
    {
        $db = DB::connection('period');

        if (in_array($type, ['sales', 'return'], true)) {
            $documentType = $type === 'sales' ? 'sales_invoice' : 'sales_return';
            $row = $db->table('document_lines as l')
                ->join('documents as d', 'd.id', '=', 'l.document_id')
                ->where('l.id', $sourceId)
                ->where('l.line_kind', 'stock')
                ->where('d.document_type', $documentType)
                ->where('d.status', 'posted')
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')->from('document_relations as r')
                        ->where('r.relation_type', 'reversal_of')
                        ->where(function ($sub): void {
                            $sub->whereColumn('r.source_document_id', 'd.id')
                                ->orWhereColumn('r.target_document_id', 'd.id');
                        });
                })
                ->first(['l.product_id', 'l.base_quantity', 'l.quantity', 'l.source_line_id', 'd.document_date']);

            if (! $row || ! $row->product_id) {
                return null;
            }

            if ($type === 'return') {
                if (! $row->source_line_id) {
                    return null;
                }
                $original = $db->table('document_lines as l')
                    ->join('documents as d', 'd.id', '=', 'l.document_id')
                    ->where('l.id', $row->source_line_id)
                    ->where('d.document_type', 'sales_invoice')
                    ->where('d.status', 'posted')
                    ->where('l.product_id', $row->product_id)->exists();
                if (! $original) {
                    return null;
                }
            }

            return [
                'product_id' => (int) $row->product_id,
                'quantity' => (string) ($row->base_quantity ?? $row->quantity),
                'date' => (string) $row->document_date,
                'source_sales_line_id' => (int) ($row->source_line_id ?? 0),
            ];
        }

        if ($type === 'fire') {
            $row = $db->table('production_consumptions as pc')
                ->join('production_completions as c', 'c.id', '=', 'pc.production_completion_id')
                ->where('pc.id', $sourceId)
                ->whereNull('c.reversal_of_id')
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')->from('production_completions as reversal')
                        ->whereColumn('reversal.reversal_of_id', 'c.id');
                })
                ->first(['pc.component_product_id', 'pc.fire_quantity', 'c.completion_date']);

            return $row ? [
                'product_id' => (int) $row->component_product_id,
                'quantity' => (string) $row->fire_quantity,
                'date' => (string) $row->completion_date,
            ] : null;
        }

        $row = $db->table('stock_movements as sm')
            ->leftJoin('warehouse_slips as ws', function ($join): void {
                $join->on('ws.id', '=', 'sm.document_id')
                    ->where('sm.document_type', '=', 'warehouse_slip');
            })
            ->where('sm.id', $sourceId)
            ->where('sm.direction', 'out')
            ->where(function ($query): void {
                $query->where('sm.reason', 'scrap')
                    ->orWhere(function ($sub): void {
                        $sub->where('sm.reason', 'adjustment')
                            ->where('sm.document_type', 'warehouse_slip')
                            ->where('ws.direction', 'out')
                            ->where('ws.reason', 'scrap')
                            ->where('ws.status', 'posted');
                    });
            })
            ->where(function ($query): void {
                $query->whereNull('ws.id')->orWhere('ws.status', 'posted');
            })
            ->first(['sm.product_id', 'sm.quantity', 'sm.movement_date']);

        return $row ? [
            'product_id' => (int) $row->product_id,
            'quantity' => (string) $row->quantity,
            'date' => (string) $row->movement_date,
        ] : null;
    }
}
