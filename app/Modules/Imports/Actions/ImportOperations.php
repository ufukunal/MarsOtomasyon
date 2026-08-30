<?php

namespace App\Modules\Imports\Actions;

use App\Foundation\Clock\Clock;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\GoodsReceipts\Actions\ApplyGoodsReceiptCostAdjustment;
use App\Modules\GoodsReceipts\Models\GoodsReceiptLine;
use App\Modules\Imports\Models\ImportContainer;
use App\Modules\Imports\Models\ImportExpense;
use App\Modules\Imports\Models\ImportFile;
use App\Modules\Imports\Models\ImportItem;
use App\Modules\Imports\Models\ImportLandedCostAllocation;
use App\Modules\Imports\Models\ImportLandedCostBatch;
use App\Modules\Imports\Models\ImportReceiptLink;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class ImportOperations
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private ApplyGoodsReceiptCostAdjustment $costAdjustments,
        private Clock $clock,
    ) {}

    public function createFile(
        string $number,
        string $currencyCode,
        ?int $supplierAccountId = null,
        ?string $supplierReference = null,
        ?string $originCountry = null,
        ?string $loadingPort = null,
        ?string $destinationPort = null,
        ?string $departureDate = null,
        ?string $expectedArrivalDate = null,
        ?string $note = null,
    ): ImportFile {
        $companyId = $this->companyId();
        $number = $this->requiredText($number, 64, 'İthalat dosya numarası');
        $currencyCode = $this->currency($currencyCode);

        return ImportFile::query()->create([
            'company_id' => $companyId,
            'supplier_account_id' => $supplierAccountId,
            'number' => $number,
            'status' => 'draft',
            'currency_code' => $currencyCode,
            'supplier_reference' => $this->optionalText($supplierReference, 100, 'Tedarikçi referansı'),
            'origin_country' => $this->optionalText($originCountry, 100, 'Menşe ülke'),
            'loading_port' => $this->optionalText($loadingPort, 150, 'Yükleme limanı'),
            'destination_port' => $this->optionalText($destinationPort, 150, 'Varış limanı'),
            'departure_date' => $departureDate,
            'expected_arrival_date' => $expectedArrivalDate,
            'note' => $this->optionalText($note, 2000, 'Not'),
        ]);
    }

    public function addContainer(
        int $fileId,
        string $containerNo,
        ?string $sealNo = null,
        ?string $containerType = null,
        ?string $maxWeightKg = null,
        ?string $maxVolumeM3 = null,
        ?string $note = null,
    ): ImportContainer {
        $companyId = $this->companyId();

        return DB::transaction(function () use ($companyId, $fileId, $containerNo, $sealNo, $containerType, $maxWeightKg, $maxVolumeM3, $note): ImportContainer {
            $file = $this->lockFile($companyId, $fileId);
            $this->assertEditable($file);

            return ImportContainer::query()->create([
                'company_id' => $companyId,
                'import_file_id' => $fileId,
                'container_no' => $this->requiredText($containerNo, 32, 'Konteyner numarası'),
                'seal_no' => $this->optionalText($sealNo, 64, 'Mühür numarası'),
                'container_type' => $this->optionalText($containerType, 32, 'Konteyner tipi'),
                'max_weight_kg' => $maxWeightKg === null ? null : $this->positiveDecimal($maxWeightKg, 'Azami ağırlık'),
                'max_volume_m3' => $maxVolumeM3 === null ? null : $this->positiveDecimal($maxVolumeM3, 'Azami hacim'),
                'note' => $this->optionalText($note, 2000, 'Konteyner notu'),
            ]);
        });
    }

    public function addItem(
        int $fileId,
        int $productId,
        string $quantity,
        ?int $containerId = null,
        ?string $packageReference = null,
        ?string $componentReference = null,
        int $packageCount = 0,
        string $grossWeightKg = '0',
        string $netWeightKg = '0',
        string $volumeM3 = '0',
        ?string $materialLocation = null,
        bool $subcontractCollection = false,
        ?string $note = null,
    ): ImportItem {
        $companyId = $this->companyId();
        if ($packageCount < 0) {
            throw ValidationException::withMessages(['package_count' => 'Paket adedi negatif olamaz.']);
        }

        return DB::transaction(function () use ($companyId, $fileId, $productId, $quantity, $containerId, $packageReference, $componentReference, $packageCount, $grossWeightKg, $netWeightKg, $volumeM3, $materialLocation, $subcontractCollection, $note): ImportItem {
            $file = $this->lockFile($companyId, $fileId);
            $this->assertEditable($file);

            return ImportItem::query()->create([
                'company_id' => $companyId,
                'import_file_id' => $fileId,
                'import_container_id' => $containerId,
                'product_id' => $productId,
                'package_reference' => $this->optionalText($packageReference, 100, 'Paket referansı'),
                'component_reference' => $this->optionalText($componentReference, 100, 'Komponent referansı'),
                'quantity' => $this->positiveDecimal($quantity, 'İthalat kalem miktarı'),
                'package_count' => $packageCount,
                'gross_weight_kg' => $this->nonNegativeDecimal($grossWeightKg, 'Brüt ağırlık'),
                'net_weight_kg' => $this->nonNegativeDecimal($netWeightKg, 'Net ağırlık'),
                'volume_m3' => $this->nonNegativeDecimal($volumeM3, 'Hacim'),
                'material_location' => $this->optionalText($materialLocation, 2000, 'Malzeme konumu'),
                'subcontract_collection' => $subcontractCollection,
                'note' => $this->optionalText($note, 2000, 'Kalem notu'),
            ]);
        });
    }

    public function recordExpense(
        int $fileId,
        string $expenseCode,
        string $description,
        string $amount,
        string $currencyCode,
        string $allocationBasis = 'line_value',
        bool $final = false,
        ?string $note = null,
    ): ImportExpense {
        $companyId = $this->companyId();
        $basis = $this->basis($allocationBasis);
        $currencyCode = $this->currency($currencyCode);

        return DB::transaction(function () use ($companyId, $fileId, $expenseCode, $description, $amount, $currencyCode, $basis, $final, $note): ImportExpense {
            $file = $this->lockFile($companyId, $fileId);
            if (in_array($file->status, ['completed', 'cancelled'], true)) {
                throw new DomainException('Kapanmış ithalat dosyasına masraf eklenemez.');
            }
            if ($currencyCode !== $file->currency_code) {
                throw ValidationException::withMessages(['currency_code' => 'M16 V1 landed-cost posting aynı para birimiyle çalışır.']);
            }

            return ImportExpense::query()->create([
                'company_id' => $companyId,
                'import_file_id' => $fileId,
                'expense_code' => $this->requiredText($expenseCode, 64, 'Masraf kodu'),
                'description' => $this->requiredText($description, 200, 'Masraf açıklaması'),
                'amount' => $this->positiveDecimal($amount, 'Masraf tutarı'),
                'currency_code' => $currencyCode,
                'status' => $final ? 'final' : 'provisional',
                'allocation_basis' => $basis,
                'finalized_at' => $final ? $this->clock->now() : null,
                'note' => $this->optionalText($note, 2000, 'Masraf notu'),
            ]);
        });
    }

    public function finalizeExpense(int $fileId, int $expenseId): ImportExpense
    {
        $companyId = $this->companyId();

        return DB::transaction(function () use ($companyId, $fileId, $expenseId): ImportExpense {
            $file = $this->lockFile($companyId, $fileId);
            if (in_array($file->status, ['completed', 'cancelled'], true)) {
                throw new DomainException('Kapanmış ithalat dosyasında masraf kesinleştirilemez.');
            }
            $expense = ImportExpense::query()->where('company_id', $companyId)->where('import_file_id', $fileId)->whereKey($expenseId)->lockForUpdate()->first();
            if (! $expense instanceof ImportExpense) {
                throw ValidationException::withMessages(['expense' => 'İthalat masrafı bulunamadı.']);
            }
            if ($expense->status === 'final') {
                return $expense;
            }
            $expense->forceFill(['status' => 'final', 'finalized_at' => $this->clock->now()])->save();

            return $expense->refresh();
        });
    }

    public function markInTransit(int $fileId): ImportFile
    {
        return $this->transition($fileId, ['draft'], 'in_transit');
    }

    public function markArrived(int $fileId, ?string $arrivalDate = null): ImportFile
    {
        $companyId = $this->companyId();

        return DB::transaction(function () use ($companyId, $fileId, $arrivalDate): ImportFile {
            $file = $this->lockFile($companyId, $fileId);
            if ($file->status === 'arrived' || $file->status === 'receiving') {
                return $file;
            }
            if ($file->status !== 'in_transit') {
                throw new DomainException('İthalat dosyası yalnız yoldayken varışa alınabilir.');
            }
            $file->forceFill(['status' => 'arrived', 'arrival_date' => $arrivalDate ?? $this->clock->now()->format('Y-m-d')])->save();

            return $file->refresh();
        });
    }

    public function linkReceiptLine(int $fileId, int $itemId, int $goodsReceiptLineId): ImportReceiptLink
    {
        $companyId = $this->companyId();

        return DB::transaction(function () use ($companyId, $fileId, $itemId, $goodsReceiptLineId): ImportReceiptLink {
            $file = $this->lockFile($companyId, $fileId);
            if (! in_array($file->status, ['arrived', 'receiving'], true)) {
                throw new DomainException('Mal kabul handoff yalnız varmış/tesellümdeki ithalat dosyasında yapılabilir.');
            }
            $item = ImportItem::query()->where('company_id', $companyId)->where('import_file_id', $fileId)->whereKey($itemId)->lockForUpdate()->first();
            if (! $item instanceof ImportItem) {
                throw ValidationException::withMessages(['item' => 'İthalat kalemi bulunamadı.']);
            }
            $line = GoodsReceiptLine::query()->where('company_id', $companyId)->whereKey($goodsReceiptLineId)->first();
            if (! $line instanceof GoodsReceiptLine) {
                throw ValidationException::withMessages(['goods_receipt_line' => 'Mal kabul satırı bulunamadı.']);
            }
            $existing = ImportReceiptLink::query()->where('company_id', $companyId)->where('goods_receipt_id', $line->goods_receipt_id)->where('goods_receipt_line_id', $goodsReceiptLineId)->first();
            if ($existing instanceof ImportReceiptLink) {
                if ((int) $existing->import_item_id !== $itemId) {
                    throw new DomainException('Mal kabul satırı farklı ithalat kalemine daha önce bağlanmış.');
                }

                return $existing;
            }

            $link = ImportReceiptLink::query()->create([
                'company_id' => $companyId,
                'import_file_id' => $fileId,
                'import_item_id' => $itemId,
                'goods_receipt_id' => (int) $line->goods_receipt_id,
                'goods_receipt_line_id' => $goodsReceiptLineId,
                'linked_quantity' => (string) $line->accepted_quantity,
            ]);
            if ($file->status === 'arrived') {
                $file->forceFill(['status' => 'receiving'])->save();
            }

            return $link;
        });
    }

    public function postLandedCost(int $fileId, string $operationKey, string $allocationBasis = 'line_value'): ImportLandedCostBatch
    {
        $companyId = $this->companyId();
        $operationKey = $this->operationKey($operationKey);
        $basis = $this->basis($allocationBasis);

        return DB::transaction(function () use ($companyId, $fileId, $operationKey, $basis): ImportLandedCostBatch {
            $file = $this->lockFile($companyId, $fileId);
            if (! in_array($file->status, ['arrived', 'receiving'], true)) {
                throw new DomainException('Landed-cost yalnız varmış/tesellümdeki ithalat dosyasına post edilebilir.');
            }

            $existing = ImportLandedCostBatch::query()->where('company_id', $companyId)->where('import_file_id', $fileId)->where('operation_key', $operationKey)->first();
            if ($existing instanceof ImportLandedCostBatch) {
                if ($existing->allocation_basis !== $basis) {
                    throw new DomainException('Aynı landed-cost işlem anahtarı farklı dağıtım temeliyle kullanılamaz.');
                }

                return $existing->load('allocations');
            }

            $this->assertReceiptReconciled($companyId, $fileId);
            $expenses = ImportExpense::query()
                ->where('company_id', $companyId)
                ->where('import_file_id', $fileId)
                ->where('status', 'final')
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')->from('import_landed_cost_batch_expenses as used')->whereColumn('used.company_id', 'import_expenses.company_id')->whereColumn('used.import_expense_id', 'import_expenses.id');
                })
                ->lockForUpdate()
                ->get();
            if ($expenses->isEmpty()) {
                throw new DomainException('Post edilecek kesinleşmiş ve kullanılmamış ithalat masrafı yok.');
            }
            foreach ($expenses as $expense) {
                if ($expense->currency_code !== $file->currency_code) {
                    throw new DomainException('Landed-cost masrafları ithalat dosyası para birimiyle aynı olmalıdır.');
                }
            }

            $totalRow = DB::selectOne('SELECT CAST(SUM(CAST(amount AS numeric)) AS numeric(20,6))::text AS total FROM import_expenses WHERE company_id = ? AND id = ANY(?::bigint[])', [$companyId, '{'.implode(',', $expenses->modelKeys()).'}']);
            if ($totalRow === null) {
                throw new LogicException('Landed-cost toplamı hesaplanamadı.');
            }
            $expenseTotal = (string) $totalRow->total;

            $allocations = DB::select(<<<'SQL'
WITH weights AS (
    SELECT link.id AS link_id,
           CASE WHEN ? = 'line_value'
                THEN CAST(link.linked_quantity * line.provisional_unit_cost AS numeric(20,6))
                ELSE CAST(link.linked_quantity AS numeric(20,6))
           END AS weight,
           ROW_NUMBER() OVER (ORDER BY link.id) AS rn,
           COUNT(*) OVER () AS cnt
    FROM import_receipt_links link
    JOIN goods_receipt_lines line
      ON line.company_id = link.company_id
     AND line.goods_receipt_id = link.goods_receipt_id
     AND line.id = link.goods_receipt_line_id
    JOIN purchase_orders purchase_order
      ON purchase_order.company_id = line.company_id
     AND purchase_order.id = line.purchase_order_id
    WHERE link.company_id = ?
      AND link.import_file_id = ?
      AND purchase_order.currency_code = ?
), rounded AS (
    SELECT link_id, weight, rn, cnt,
           CAST(ROUND(CAST(? AS numeric) * weight / SUM(weight) OVER (), 6) AS numeric(20,6)) AS preliminary
    FROM weights
), finalized AS (
    SELECT link_id, weight, rn, cnt, preliminary,
           CAST(CASE WHEN rn = cnt
                THEN CAST(? AS numeric) - SUM(CASE WHEN rn < cnt THEN preliminary ELSE 0 END) OVER ()
                ELSE preliminary END AS numeric(20,6)) AS allocated_amount
    FROM rounded
)
SELECT link_id, CAST(weight AS numeric(20,6))::text AS weight, allocated_amount::text AS allocated_amount
FROM finalized
ORDER BY link_id
SQL, [$basis, $companyId, $fileId, $file->currency_code, $expenseTotal, $expenseTotal]);
            if ($allocations === []) {
                throw new DomainException('Landed-cost dağıtımı için uyumlu mal kabul bağlantısı bulunamadı.');
            }
            foreach ($allocations as $allocation) {
                if (! $this->greaterThan((string) $allocation->weight, '0') || ! $this->greaterThan((string) $allocation->allocated_amount, '0')) {
                    throw new DomainException('Landed-cost dağıtım ağırlıkları ve tutarları pozitif olmalıdır.');
                }
            }

            $batch = ImportLandedCostBatch::query()->create([
                'company_id' => $companyId,
                'import_file_id' => $fileId,
                'operation_key' => $operationKey,
                'allocation_basis' => $basis,
                'expense_total' => $expenseTotal,
                'currency_code' => $file->currency_code,
                'posted_at' => $this->clock->now(),
            ]);
            foreach ($expenses as $expense) {
                DB::table('import_landed_cost_batch_expenses')->insert([
                    'company_id' => $companyId,
                    'landed_cost_batch_id' => $batch->getKey(),
                    'import_expense_id' => $expense->getKey(),
                    'amount_snapshot' => (string) $expense->amount,
                    'created_at' => $this->clock->now(),
                    'updated_at' => $this->clock->now(),
                ]);
            }

            foreach ($allocations as $allocation) {
                $link = ImportReceiptLink::query()->where('company_id', $companyId)->whereKey((int) $allocation->link_id)->firstOrFail();
                $reference = 'IMP-'.$fileId.'-'.substr(hash('sha256', $operationKey.'|'.$link->getKey()), 0, 24);
                $adjustment = $this->costAdjustments->handle(
                    (int) $link->goods_receipt_id,
                    (int) $link->goods_receipt_line_id,
                    $reference,
                    (string) $allocation->allocated_amount,
                    'İthalat landed-cost '.$file->number.' / '.$operationKey,
                );
                ImportLandedCostAllocation::query()->create([
                    'company_id' => $companyId,
                    'landed_cost_batch_id' => $batch->getKey(),
                    'import_receipt_link_id' => $link->getKey(),
                    'goods_receipt_cost_adjustment_id' => $adjustment->getKey(),
                    'allocation_weight' => (string) $allocation->weight,
                    'allocated_amount' => (string) $allocation->allocated_amount,
                ]);
            }

            return $batch->load('allocations');
        });
    }

    public function complete(int $fileId): ImportFile
    {
        $companyId = $this->companyId();

        return DB::transaction(function () use ($companyId, $fileId): ImportFile {
            $file = $this->lockFile($companyId, $fileId);
            if ($file->status === 'completed') {
                return $file;
            }
            if (! in_array($file->status, ['arrived', 'receiving'], true)) {
                throw new DomainException('İthalat dosyası yalnız varış/tesellüm sonrasında tamamlanabilir.');
            }
            $this->assertReceiptReconciled($companyId, $fileId);
            if (ImportExpense::query()->where('company_id', $companyId)->where('import_file_id', $fileId)->where('status', 'provisional')->exists()) {
                throw new DomainException('Tamamlamadan önce provisional ithalat masrafları kesinleştirilmelidir.');
            }
            $unposted = ImportExpense::query()->where('company_id', $companyId)->where('import_file_id', $fileId)->where('status', 'final')->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('import_landed_cost_batch_expenses as used')->whereColumn('used.company_id', 'import_expenses.company_id')->whereColumn('used.import_expense_id', 'import_expenses.id');
            })->exists();
            if ($unposted) {
                throw new DomainException('Tamamlamadan önce tüm kesinleşmiş ithalat masrafları landed-cost olarak post edilmelidir.');
            }

            $file->forceFill(['status' => 'completed', 'completed_at' => $this->clock->now()])->save();

            return $file->refresh();
        });
    }

    /** @return array{gross_weight_kg:string,net_weight_kg:string,volume_m3:string,package_count:int,item_count:int} */
    public function loadingSummary(int $fileId): array
    {
        $companyId = $this->companyId();
        $row = DB::selectOne('SELECT CAST(COALESCE(SUM(gross_weight_kg),0) AS numeric(20,6))::text AS gross_weight_kg, CAST(COALESCE(SUM(net_weight_kg),0) AS numeric(20,6))::text AS net_weight_kg, CAST(COALESCE(SUM(volume_m3),0) AS numeric(20,6))::text AS volume_m3, COALESCE(SUM(package_count),0)::int AS package_count, COUNT(*)::int AS item_count FROM import_items WHERE company_id = ? AND import_file_id = ?', [$companyId, $fileId]);
        if ($row === null) {
            throw new LogicException('İthalat yükleme özeti hesaplanamadı.');
        }

        return ['gross_weight_kg' => (string) $row->gross_weight_kg, 'net_weight_kg' => (string) $row->net_weight_kg, 'volume_m3' => (string) $row->volume_m3, 'package_count' => (int) $row->package_count, 'item_count' => (int) $row->item_count];
    }

    /** @return Collection<int, ImportItem> */
    public function subcontractCollectionRows(int $fileId): Collection
    {
        return ImportItem::query()->where('company_id', $this->companyId())->where('import_file_id', $fileId)->where('subcontract_collection', true)->orderBy('id')->get();
    }

    /** @param list<string> $from */
    private function transition(int $fileId, array $from, string $to): ImportFile
    {
        $companyId = $this->companyId();

        return DB::transaction(function () use ($companyId, $fileId, $from, $to): ImportFile {
            $file = $this->lockFile($companyId, $fileId);
            if ($file->status === $to) {
                return $file;
            }
            if (! in_array($file->status, $from, true)) {
                throw new DomainException('Geçersiz ithalat lifecycle geçişi.');
            }
            $file->forceFill(['status' => $to])->save();

            return $file->refresh();
        });
    }

    private function assertReceiptReconciled(int $companyId, int $fileId): void
    {
        $row = DB::selectOne(<<<'SQL'
SELECT COUNT(*)::int AS mismatches
FROM import_items item
LEFT JOIN (
    SELECT company_id, import_item_id, SUM(linked_quantity) AS linked_quantity
    FROM import_receipt_links
    WHERE company_id = ? AND import_file_id = ?
    GROUP BY company_id, import_item_id
) linked ON linked.company_id = item.company_id AND linked.import_item_id = item.id
WHERE item.company_id = ?
  AND item.import_file_id = ?
  AND COALESCE(linked.linked_quantity, 0) <> item.quantity
SQL, [$companyId, $fileId, $companyId, $fileId]);
        if ($row === null || (int) $row->mismatches > 0) {
            throw new DomainException('Landed-cost/tamamlama için tüm ithalat kalemleri mal kabul ile tam reconcile edilmelidir.');
        }
        if (! ImportItem::query()->where('company_id', $companyId)->where('import_file_id', $fileId)->exists()) {
            throw new DomainException('İthalat dosyasında en az bir kalem bulunmalıdır.');
        }
    }

    private function lockFile(int $companyId, int $fileId): ImportFile
    {
        $file = ImportFile::query()->where('company_id', $companyId)->whereKey($fileId)->lockForUpdate()->first();
        if (! $file instanceof ImportFile) {
            throw ValidationException::withMessages(['import_file' => 'İthalat dosyası aktif şirkette bulunamadı.']);
        }

        return $file;
    }

    private function assertEditable(ImportFile $file): void
    {
        if (! in_array($file->status, ['draft', 'in_transit'], true)) {
            throw new DomainException('İthalat yapısal kayıtları varış sonrasında değiştirilemez.');
        }
    }

    private function companyId(): int
    {
        return (int) $this->companyContext->requireCompany()->getKey();
    }

    private function basis(string $raw): string
    {
        $value = trim($raw);
        if (! in_array($value, ['line_value', 'quantity'], true)) {
            throw ValidationException::withMessages(['allocation_basis' => 'Dağıtım temeli line_value veya quantity olmalıdır.']);
        }

        return $value;
    }

    private function currency(string $raw): string
    {
        $value = strtoupper(trim($raw));
        if (preg_match('/^[A-Z]{3}$/D', $value) !== 1) {
            throw ValidationException::withMessages(['currency_code' => 'Para birimi üç harfli ISO kodu olmalıdır.']);
        }

        return $value;
    }

    private function operationKey(string $raw): string
    {
        $value = trim($raw);
        if ($value === '' || strlen($value) > 64 || preg_match('/^[A-Za-z0-9._:-]+$/D', $value) !== 1) {
            throw ValidationException::withMessages(['operation_key' => 'İşlem anahtarı canonical ve en fazla 64 karakter olmalıdır.']);
        }

        return $value;
    }

    private function positiveDecimal(string $raw, string $label): string
    {
        $value = $this->decimal($raw, $label);
        if (! $this->greaterThan($value, '0')) {
            throw ValidationException::withMessages(['value' => $label.' sıfırdan büyük olmalıdır.']);
        }

        return $value;
    }

    private function nonNegativeDecimal(string $raw, string $label): string
    {
        $value = $this->decimal($raw, $label);
        if ($this->lessThan($value, '0')) {
            throw ValidationException::withMessages(['value' => $label.' negatif olamaz.']);
        }

        return $value;
    }

    private function decimal(string $raw, string $label): string
    {
        $value = trim($raw);
        if (preg_match('/^\d+(?:\.\d{1,6})?$/D', $value) !== 1) {
            throw ValidationException::withMessages(['value' => $label.' en fazla 6 ondalıklı sayı olmalıdır.']);
        }
        $row = DB::selectOne('SELECT CAST(CAST(? AS numeric) AS numeric(20,6))::text AS value', [$value]);
        if ($row === null) {
            throw new LogicException($label.' normalize edilemedi.');
        }

        return (string) $row->value;
    }

    private function requiredText(string $raw, int $max, string $label): string
    {
        $value = trim($raw);
        if ($value === '' || mb_strlen($value) > $max) {
            throw ValidationException::withMessages(['value' => $label.' zorunlu ve en fazla '.$max.' karakter olmalıdır.']);
        }

        return $value;
    }

    private function optionalText(?string $raw, int $max, string $label): ?string
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $max) {
            throw ValidationException::withMessages(['value' => $label.' en fazla '.$max.' karakter olmalıdır.']);
        }

        return $value;
    }

    private function greaterThan(string $left, string $right): bool
    {
        $row = DB::selectOne('SELECT CAST(? AS numeric) > CAST(? AS numeric) AS value', [$left, $right]);

        return $row?->value === true;
    }

    private function lessThan(string $left, string $right): bool
    {
        $row = DB::selectOne('SELECT CAST(? AS numeric) < CAST(? AS numeric) AS value', [$left, $right]);

        return $row?->value === true;
    }
}
