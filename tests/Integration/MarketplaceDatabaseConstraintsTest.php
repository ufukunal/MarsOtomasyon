<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\Support\IsolatedPostgres;

it('restricts marketplace accounts to explicitly supported providers', function (): void {
    IsolatedPostgres::withActivePeriod(function (int $companyId): void {
        $master = DB::connection('master');
        $row = [
            'company_id' => $companyId,
            'platform' => 'unknown_marketplace',
            'name' => 'V4 invalid platform',
            'credentials_encrypted' => 'never a real credential',
            'created_at' => now(), 'updated_at' => now(),
        ];
        $master->beginTransaction();
        $rejected = false;
        try {
            $master->table('sales_channel_accounts')->insert($row);
        } catch (QueryException) {
            $rejected = true;
        } finally {
            $master->rollBack();
        }
        expect($rejected)->toBeTrue();
        expect($master->table('sales_channel_accounts')
            ->where('company_id', $companyId)->count())->toBe(0);
    });
});

it('requires the four ecommerce webhook routes and their HTTP methods', function (string $name, string $method): void {
    $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName($name);
    expect($route)->not->toBeNull()
        ->and($route->methods())->toContain($method)
        ->and($route->gatherMiddleware())->toContain('throttle:webhook');
})->with([
    ['webhooks.trendyol', 'POST'],
    ['webhooks.hepsiburada', 'PUT'],
    ['webhooks.woocommerce', 'POST'],
]);
