<?php

use Illuminate\Support\Facades\Schema;
use Tests\Support\IsolatedPostgres;

it('verifies core read and write schema contracts for each business module', function (string $database, array $tables): void {
    IsolatedPostgres::approved();
    foreach ($tables as $table) {
        expect(Schema::connection($database)->hasTable($table))
            ->toBeTrue("Missing module table: {$database}.{$table}");
    }
})->with([
    'catalog' => ['period', ['contacts', 'products', 'product_categories', 'brands', 'units', 'price_lists', 'price_list_items']],
    'inventory' => ['period', ['stock_balances', 'stock_movements', 'stock_counts', 'stock_reservations', 'transfers', 'warehouse_slips']],
    'sales-and-invoicing' => ['period', ['documents', 'document_lines', 'document_relations']],
    'purchasing' => ['period', ['purchase_matches', 'documents', 'document_lines']],
    'finance' => ['period', ['cash_accounts', 'cash_movements', 'bank_accounts', 'bank_movements', 'contact_transactions']],
    'returns' => ['period', ['documents', 'document_lines', 'quarantine_entries']],
    'imports' => ['period', ['import_files', 'packages', 'import_cost_items', 'import_cost_allocations']],
    'production' => ['period', ['production_orders', 'production_recipes', 'production_completions', 'production_consumptions']],
    'channels' => ['period', ['channel_product_listings', 'channel_sync_events', 'channel_sync_errors']],
    'reporting' => ['master', ['report_filter_presets', 'report_export_jobs', 'document_templates', 'print_jobs']],
    'operations' => ['master', ['backup_runs', 'restore_runs', 'deployment_runs', 'health_check_runs']],
    'access' => ['master', ['users', 'companies', 'periods', 'company_user', 'period_user_access']],
]);
