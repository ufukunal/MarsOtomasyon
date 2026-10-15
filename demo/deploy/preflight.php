<?php

declare(strict_types=1);
use App\Models\Period;
use App\Support\Period\PeriodContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Read-only Laravel/DB readiness check for the isolated mars-uat-fresh stack.
// Runs inside a ONE-OFF web container without publishing network ports.

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

try {
    $db = DB::connection('master')->getDatabaseName();
    if ($db !== 'mars_uat_master') {
        throw new RuntimeException('BLOCKED: unexpected Master DB: '.$db);
    }
    if (app()->environment('production')) {
        throw new RuntimeException('BLOCKED: app environment is production');
    }
    $periods = Period::query()
        ->where('database_name', 'mars_uat_demo_2026')->get();
    if ($periods->count() !== 1) {
        throw new RuntimeException('BLOCKED: UAT 2026 period has not been created by /kurulum (or is ambiguous)');
    }
    $period = $periods->first();
    if ((int) $period->year !== 2026 || $period->status !== 'active') {
        throw new RuntimeException('BLOCKED: target period must be active, year 2026');
    }

    PeriodContext::withinSystem($period, function (): void {
        $connection = DB::connection('period');
        if ($connection->getDatabaseName() !== 'mars_uat_demo_2026') {
            throw new RuntimeException('BLOCKED: period connection mismatch');
        }
        foreach ([
            'products', 'brands', 'product_categories', 'contacts', 'contact_addresses',
            'contact_people', 'locations', 'units', 'stock_balances', 'stock_movements',
            'documents', 'document_lines', 'warehouse_slips', 'warehouse_slip_lines',
            'transfers', 'transfer_lines', 'transport_slips', 'transport_slip_items',
            'import_files', 'containers', 'packages', 'import_cost_items',
            'production_recipes', 'production_recipe_lines', 'production_orders',
            'production_order_components', 'attachments',
        ] as $table) {
            if (! Schema::connection('period')->hasTable($table)) {
                throw new RuntimeException('BLOCKED: missing period table: '.$table);
            }
        }
        $products = $connection->table('products')->where('code', 'like', 'DM26-P%')->count();
        $docs = $connection->table('documents')->where('number', 'like', 'DM26-%')->count();
        $contacts = $connection->table('contacts')->where('title', 'like', 'DEMO · %')->count();
        echo 'PASS: Master=mars_uat_master Period=mars_uat_demo_2026 Active=YES Schema=OK'.PHP_EOL;
        echo "EXISTING_DEMO: products={$products} documents={$docs} demo_title_contacts={$contacts}".PHP_EOL;
        echo 'NOTE: Existing demo records may mean this is a repeat import; no data is deleted.'.PHP_EOL;
    });
} catch (Throwable $e) {
    fwrite(STDERR, 'PREFLIGHT FAILED: '.$e->getMessage().PHP_EOL);
    exit(3);
}
