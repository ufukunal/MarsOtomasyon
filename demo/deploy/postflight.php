<?php

declare(strict_types=1);

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    if (\Illuminate\Support\Facades\DB::connection('master')->getDatabaseName() !== 'mars_uat_master') {
        throw new \RuntimeException('wrong Master database');
    }
    $period = \App\Models\Period::query()->where('database_name', 'mars_uat_demo_2026')->firstOrFail();
    \App\Support\Period\PeriodContext::withinSystem($period, function (): void {
        $db = \Illuminate\Support\Facades\DB::connection('period');
        $queries = [
            'products' => [250, $db->table('products')->where('code', 'like', 'DM26-P%')->count()],
            'locations' => [10, $db->table('locations')->where('code', 'like', 'DM26-L%')->count()],
            'documents' => [1210, $db->table('documents')->where('number', 'like', 'DM26-%')->count()],
            'document_lines' => [2740, $db->table('document_lines as l')
                ->join('documents as d', 'd.id', '=', 'l.document_id')
                ->where('d.number', 'like', 'DM26-%')->count()],
            'warehouse_slips' => [100, $db->table('warehouse_slips')->where('number', 'like', 'DM26-WS-%')->count()],
            'transfers' => [50, $db->table('transfers')->where('number', 'like', 'DM26-TR-%')->count()],
            'transport_slips' => [100, $db->table('transport_slips')->where('number', 'like', 'DM26-TS-%')->count()],
            'import_files' => [4, $db->table('import_files')->where('number', 'like', 'DM26-IMP-%')->count()],
            'containers' => [4, $db->table('containers as c')->join('import_files as f','f.id','=','c.import_file_id')
                ->where('f.number', 'like', 'DM26-IMP-%')->count()],
            'packages' => [120, $db->table('packages as p')->join('import_files as f','f.id','=','p.import_file_id')
                ->where('f.number', 'like', 'DM26-IMP-%')->count()],
            'production_recipes' => [60, $db->table('production_recipes')->where('number', 'like', 'DM26-BOM-%')->count()],
            'production_orders' => [40, $db->table('production_orders')->where('number', 'like', 'DM26-MO-%')->count()],
            'stock_openings' => [750, $db->table('stock_movements')->where('document_no', 'like', 'DM26-OPEN-%')->count()],
            'attachments' => [750, $db->table('attachments')->where('path', 'like', 'uat-demo/rich/%')->count()],
        ];
        $contactsCsv = '/var/www/html/storage/app/uat-demo-rich/csv/contacts.csv';
        $fp = fopen($contactsCsv, 'rb');
        if (!$fp) { throw new \RuntimeException('missing contacts CSV'); }
        $header = fgetcsv($fp, 0, ',', '"', '\\');
        if (is_array($header)) { $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]); }
        $names = [];
        while (($row = fgetcsv($fp, 0, ',', '"', '\\')) !== false) {
            $data = array_combine($header, $row);
            $names[] = $data['name'];
        }
        fclose($fp);
        $queries['contacts'] = [100, $db->table('contacts')->whereIn('title', $names)->count()];
        $queries['stock_balances'] = [750, $db->table('stock_balances as b')
            ->join('products as p','p.id','=','b.product_id')->where('p.code','like','DM26-P%')->count()];

        $errors = 0;
        foreach ($queries as $name => [$expected, $actual]) {
            $okay = $expected === $actual;
            printf("%s %-22s expected=%d actual=%d\n", $okay ? 'PASS' : 'FAIL', $name, $expected, $actual);
            if (!$okay) { $errors++; }
        }
        $posted = $db->table('documents')->where('number','like','DM26-%')->where('status','posted')->count();
        printf("%s posted_demo_documents=%d (must remain zero at seeding)\n", $posted === 0 ? 'PASS' : 'FAIL', $posted);
        if ($posted !== 0) { $errors++; }
        if ($errors > 0) { throw new \RuntimeException("{$errors} database count/integrity checks failed"); }
        echo 'PASS: isolated fresh demo importer core counts and draft statuses verified'.PHP_EOL;
    });
} catch (\Throwable $e) {
    fwrite(STDERR, 'POSTFLIGHT FAILED: '.$e->getMessage().PHP_EOL);
    exit(3);
}
