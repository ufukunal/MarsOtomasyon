<?php

use App\Support\Reporting\Queries\ImportSalesReportQuery;
use App\Support\Reporting\Queries\ImportProductReportQuery;
use App\Support\Reporting\Queries\ImportContainerProductReportQuery;
use App\Support\Reporting\ReportExecutionContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\IsolatedPostgres;

it('lists quarterly imported-product invoice and return lines without multiplying shared import candidates', function (): void {
    IsolatedPostgres::withActivePeriod(function (): void {
        $db = DB::connection('period');
        $ids = IsolatedPostgres::productAndLocation();
        $code = Str::random(10);
        $now = now();

        $contact = $db->table('contacts')->insertGetId([
            'title' => 'Import report buyer '.$code,
            'type' => 'legal',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        foreach ([
            ['number' => 'IMP-R1-'.$code, 'received' => '2026-01-15'],
            ['number' => 'IMP-R2-'.$code, 'received' => '2026-02-15'],
        ] as $entry) {
            $importId = $db->table('import_files')->insertGetId([
                'number' => $entry['number'],
                'supplier_contact_id' => $contact,
                'currency' => 'TRY',
                'exchange_rate' => 1,
                'status' => 'received',
                'received_at' => $entry['received'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $containerId = $db->table('containers')->insertGetId([
                'import_file_id' => $importId,
                'container_no' => 'CNT-'.$code.'-'.$importId,
                'status' => 'received',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $db->table('packages')->insert([
                'import_file_id' => $importId,
                'container_id' => $containerId,
                'carton_no' => 'PKG-'.$code.'-'.$importId,
                'product_id' => $ids['product'],
                'location_id' => $ids['location'],
                'status' => 'received',
                'quantity' => 100,
                'unit_price' => 100,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $firstImportId = (int) $db->table('import_files')
            ->where('number', 'IMP-R1-'.$code)->value('id');
        $firstContainerId = (int) $db->table('containers')
            ->where('import_file_id', $firstImportId)->value('id');
        $db->table('packages')->insert([
            'import_file_id' => $firstImportId,
            'container_id' => $firstContainerId,
            'carton_no' => 'PKG-EXTRA-'.$code,
            'product_id' => $ids['product'],
            'location_id' => $ids['location'],
            'status' => 'received',
            'quantity' => 10,
            'unit_price' => 100,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $createLine = function (string $type, string $date, string $status, string $name, int $quantity, int $headerDiscount = 0) use ($db, $ids, $contact, $now): void {
            $subtotal = $quantity * 1000;
            $documentId = $db->table('documents')->insertGetId([
                'document_type' => $type,
                'number' => $name,
                'document_date' => $date,
                'contact_id' => $contact,
                'status' => $status,
                'currency' => 'TRY',
                'subtotal' => $subtotal,
                'discount_amount' => $headerDiscount,
                'tax_base' => $subtotal - $headerDiscount,
                'vat_amount' => ($subtotal - $headerDiscount) * .20,
                'grand_total' => ($subtotal - $headerDiscount) * 1.20,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $db->table('document_lines')->insert([
                'document_id' => $documentId,
                'line_no' => 1,
                'line_kind' => 'stock',
                'product_id' => $ids['product'],
                'unit_id' => $ids['unit'],
                'location_id' => $ids['location'],
                'quantity' => $quantity,
                'base_quantity' => $quantity,
                'conversion_factor' => 1,
                'unit_price' => 1000,
                'vat_rate' => 20,
                'line_total' => $subtotal,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        };

        $createLine('sales_invoice', '2026-01-10', 'posted', 'EARLY-'.$code, 1);
        $createLine('sales_invoice', '2026-02-20', 'posted', 'INVOICE-'.$code, 2, 200);
        $createLine('sales_return', '2026-03-01', 'posted', 'RETURN-'.$code, 1);
        $createLine('sales_invoice', '2026-03-02', 'draft', 'DRAFT-'.$code, 1);
        $createLine('sales_invoice', '2026-04-01', 'posted', 'APRIL-'.$code, 1);

        $productCode = (string) $db->table('products')->where('id', $ids['product'])->value('code');

        $query = new ImportSalesReportQuery;
        $definition = $query->definition();
        $context = fn (int $quarter, array $extra = []): ReportExecutionContext => new ReportExecutionContext(
            filters: ['year' => 2026, 'quarter' => $quarter, ...$extra],
            columns: $definition->defaultColumnKeys(true),
            sort: $definition->defaultSort,
            totalKeys: ['net_amount', 'vat_amount', 'grand_total'],
            limit: 50,
            offset: 0,
            canViewCost: true,
        );

        $q1 = $query->execute($context(1));
        expect($q1->totalRows)->toBe(2)
            ->and($q1->rows)->toHaveCount(2)
            ->and($q1->rows[0]['number'])->toBe('INVOICE-'.$code)
            ->and($q1->rows[1]['number'])->toBe('RETURN-'.$code)
            ->and((float) $q1->rows[0]['net_amount'])->toBe(1800.0)
            ->and((float) $q1->rows[0]['document_discount_share'])->toBe(200.0)
            ->and((float) $q1->rows[1]['net_amount'])->toBe(-1000.0)
            ->and($q1->rows[0]['possible_import_files'])->toContain('IMP-R1-'.$code)
            ->and($q1->rows[0]['possible_import_files'])->toContain('IMP-R2-'.$code)
            ->and($q1->rows[0]['attribution_status'])->toBe('Parti doğrulanmadı')
            ->and($q1->rows[0]['possible_containers'])->toContain('CNT-'.$code)
            ->and($q1->totals['net_amount'])->toContain('800')
            ->and($q1->totals['net_amount'])->toContain('TRY');

        $firstContainerNo = (string) $db->table('containers as ct')
            ->join('import_files as f', 'f.id', '=', 'ct.import_file_id')
            ->where('f.number', 'IMP-R1-'.$code)->value('ct.container_no');

        expect($query->execute($context(1, ['container_no' => $firstContainerNo]))->totalRows)->toBe(2);
        expect($query->execute($context(1, [
            'container_no' => $firstContainerNo,
            'import_number' => 'IMP-R2-'.$code,
        ]))->totalRows)->toBe(0);

        $sourceContext = static function (array $filters, $definition): ReportExecutionContext {
            return new ReportExecutionContext(
                filters: $filters,
                columns: $definition->defaultColumnKeys(true),
                sort: $definition->defaultSort,
                totalKeys: [],
                limit: 50,
                offset: 0,
                canViewCost: true,
            );
        };

        $importProducts = new ImportProductReportQuery;
        $fileResults = $importProducts->execute($sourceContext([], $importProducts->definition()));
        $containerProducts = new ImportContainerProductReportQuery;
        $containerResults = $containerProducts->execute($sourceContext([], $containerProducts->definition()));

        expect($fileResults->totalRows)->toBe(2)
            ->and($containerResults->totalRows)->toBe(2)
            ->and((float) $fileResults->rows[0]['import_quantity'])->toBe(110.0)
            ->and((int) $fileResults->rows[0]['container_count'])->toBe(1)
            ->and((int) $fileResults->rows[0]['package_count'])->toBe(2)
            ->and((float) $containerResults->rows[0]['import_quantity'])->toBe(110.0)
            ->and((int) $containerResults->rows[0]['package_count'])->toBe(2);

        expect($containerProducts->execute(
            $sourceContext(['container_no' => $firstContainerNo], $containerProducts->definition())
        )->totalRows)->toBe(1);
        expect($importProducts->execute(
            $sourceContext(['import_number' => 'IMP-R2-'.$code], $importProducts->definition())
        )->totalRows)->toBe(1);

        expect($query->execute($context(2))->totalRows)->toBe(1);
        expect($query->execute($context(1, ['product_code' => $productCode]))->totalRows)->toBe(2);
        expect($query->execute($context(1, ['import_number' => 'IMP-R1-'.$code]))->totalRows)->toBe(2);
        expect($query->execute($context(1, ['import_number' => 'IMP-R2-'.$code]))->totalRows)->toBe(2);
        expect($query->execute($context(1, ['import_number' => 'NOT-FOUND-'.$code]))->totalRows)->toBe(0);
        expect($query->execute($context(1, ['contact_code' => 'MISSING-'.$code]))->totalRows)->toBe(0);
        expect((float) $q1->rows[1]['document_discount_share'])->toBe(0.0);
        expect(fn () => $query->execute($context(1, ['year' => 2025])))
            ->toThrow(DomainException::class);
    });
});
