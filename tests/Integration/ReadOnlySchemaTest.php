<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function requireDedicatedMarsIntegrationDatabases(): void
{
    if (getenv('MARS_INTEGRATION_TESTS_APPROVED') !== 'I_APPROVE_LOCAL_TEST_ONLY') {
        test()->markTestSkipped('Explicit local test DB approval required; never use production DB.');
    }

    expect(config('database.connections.master.host'))->toBe('127.0.0.1')
        ->and(config('database.connections.master.database'))->toBe('mars_test_master')
        ->and(config('database.connections.master.username'))->toBe('mars_test');

    config(['database.connections.period.host' => '127.0.0.1']);
    config(['database.connections.period.username' => 'mars_test']);
    config(['database.connections.period.database' => 'mars_test_period']);
    DB::purge('period');
}

it('checks the tables created by every master and period migration without mutating any schema', function (string $connection, string $migrationDirectory): void {
    requireDedicatedMarsIntegrationDatabases();
    $tableNames = [];
    foreach (glob(database_path("migrations/{$migrationDirectory}/*.php")) ?: [] as $path) {
        $source = file_get_contents($path);
        preg_match_all('~(?:->|::)create\(\s*[\\x27\\x22]([a-z_][a-z0-9_]*)[\\x27\\x22]~', $source, $matches);
        array_push($tableNames, ...$matches[1]);
    }
    $tableNames = array_values(array_unique($tableNames));
    expect($tableNames)->not->toBeEmpty();

    foreach ($tableNames as $table) {
        expect(Schema::connection($connection)->hasTable($table))->toBeTrue("Missing {$connection}.{$table}");
    }
})->with([
    ['master', 'master'],
    ['period', 'period'],
]);

it('checks core documents, stock, finance, production, imports and channels tables on isolated period DB', function (string $table): void {
    requireDedicatedMarsIntegrationDatabases();
    expect(Schema::connection('period')->hasTable($table))->toBeTrue("Missing period table: {$table}");
})->with([
    'products', 'contacts', 'units', 'locations', 'price_lists', 'price_list_items',
    'stock_balances', 'stock_movements', 'stock_reservations',
    'transfers', 'transfer_lines', 'warehouse_slips', 'stock_counts', 'quarantine_entries',
    'documents', 'document_lines', 'document_relations', 'contact_transactions',
    'cash_accounts', 'bank_accounts', 'purchase_matches',
    'import_files', 'import_packages', 'production_recipes', 'production_orders',
    'channel_product_listings', 'channel_sync_events',
]);

it('keeps master auth and operational records outside the period database', function (string $table): void {
    requireDedicatedMarsIntegrationDatabases();
    expect(Schema::connection('master')->hasTable($table))->toBeTrue("Missing master table: {$table}");
})->with([
    'companies', 'periods', 'users', 'company_user', 'period_user_access',
    'sales_channel_accounts', 'document_templates', 'report_export_jobs', 'print_jobs',
]);
