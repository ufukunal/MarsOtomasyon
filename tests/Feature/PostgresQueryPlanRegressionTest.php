<?php

namespace Tests\Feature;

use App\Modules\Reports\OperationalReportCatalog;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PostgresQueryPlanRegressionTest extends TestCase
{
    public function test_stock_movements_use_the_company_product_occurred_index(): void
    {
        $this->assertUsesIndex(
            'SELECT id FROM stock_movements WHERE company_id = ? AND product_id = ? ORDER BY occurred_at DESC LIMIT 25',
            [1, 1],
        );
    }

    public function test_account_transactions_use_the_statement_index(): void
    {
        $this->assertUsesIndex(
            'SELECT id FROM account_transactions WHERE company_id = ? AND account_id = ? ORDER BY posting_date DESC, id DESC LIMIT 25',
            [1, 1],
        );
    }

    public function test_outbox_dispatch_queue_uses_the_status_available_index(): void
    {
        $this->assertUsesIndex(
            'SELECT id FROM outbox_messages WHERE status = ? AND available_at <= CURRENT_TIMESTAMP ORDER BY available_at LIMIT 25',
            ['pending'],
        );
    }

    public function test_integration_queues_use_the_status_available_indexes(): void
    {
        $this->assertUsesIndex(
            'SELECT id FROM integration_events WHERE status = ? AND available_at <= CURRENT_TIMESTAMP ORDER BY available_at LIMIT 25',
            ['received'],
        );
        $this->assertUsesIndex(
            'SELECT id FROM integration_sync_effects WHERE status = ? AND available_at <= CURRENT_TIMESTAMP ORDER BY available_at LIMIT 25',
            ['queued'],
        );
    }

    public function test_representative_operational_report_uses_an_indexed_plan(): void
    {
        $query = app(OperationalReportCatalog::class)->query('SAL-01', 1);

        $this->assertUsesIndex($query->toSql(), $query->getBindings());
    }

    public function test_product_full_text_and_trigram_search_use_gin_indexes(): void
    {
        $this->assertUsesIndex(
            "SELECT id FROM products WHERE to_tsvector('simple', coalesce(code, '') || ' ' || coalesce(name, '')) @@ plainto_tsquery('simple', ?)",
            ['mars'],
        );
        $this->assertUsesIndex(
            'SELECT id FROM products WHERE lower(name) LIKE ?',
            ['%mars%'],
        );
    }

    /** @param list<mixed> $bindings */
    private function assertUsesIndex(string $sql, array $bindings = []): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            self::markTestSkipped('PostgreSQL query-plan regression only applies to pgsql.');
        }

        DB::statement('SET enable_seqscan TO off');

        try {
            $rows = DB::select('EXPLAIN '.$sql, $bindings);
            $plan = collect($rows)
                ->map(static function (object $row): string {
                    $values = array_values((array) $row);

                    return isset($values[0]) ? (string) $values[0] : '';
                })
                ->implode("\n");

            self::assertMatchesRegularExpression(
                '/(?:Index Scan|Index Only Scan|Bitmap Heap Scan|Bitmap Index Scan)/',
                $plan,
                $plan,
            );
        } finally {
            DB::statement('RESET enable_seqscan');
        }
    }
}
