<?php

namespace App\Actions\Periods;

use App\Actions\Documents\SourceLineAvailability;
use App\Actions\Purchases\PurchaseLineAvailability;
use App\DataObjects\Periods\PeriodCarryPreview;
use App\Enums\DocumentType;
use App\Models\Period;
use App\Models\Period\Document;
use App\Models\Period\DocumentLine;
use App\Support\Period\PeriodContext;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

final class PreviewPeriodCarry
{
    public function __construct(
        private readonly SourceLineAvailability $salesAvailability,
        private readonly PurchaseLineAvailability $purchaseAvailability,
    ) {}

    public function handle(Period $source, int $targetYear): PeriodCarryPreview
    {
        Gate::authorize('periods.create');
        $source->loadMissing('company');

        $checks = [];
        $this->assertSourceAccess($source);

        if ($targetYear !== (int) $source->year + 1) {
            $checks[] = $this->check(
                'target_year',
                'block',
                'Hedef dönem kaynak dönemin ardışık yılı olmalıdır.',
            );
        } else {
            $checks[] = $this->check('target_year', 'pass', 'Hedef yıl ardışık dönemdir.');
        }

        if ((string) $source->status !== 'active') {
            $checks[] = $this->check('source_status', 'block', 'Kaynak dönem active durumda olmalıdır.');
        } elseif ($source->carried_at !== null) {
            $checks[] = $this->check('source_status', 'block', 'Kaynak dönem daha önce devredilmiş.');
        } else {
            $checks[] = $this->check('source_status', 'pass', 'Kaynak dönem carry için uygundur.');
        }

        $target = Period::query()
            ->where('company_id', $source->company_id)
            ->where('year', $targetYear)
            ->first();

        $expectedDb = sprintf('%s_%d', $source->company->db_prefix, $targetYear);
        $physicalExists = DB::connection('master')
            ->table('pg_database')
            ->where('datname', $expectedDb)
            ->exists();

        if (! $target && $physicalExists) {
            $checks[] = $this->check(
                'target_database',
                'block',
                'Hedef fiziksel veritabanı Master period kaydı olmadan mevcut.',
            );
        } elseif ($target && (string) $target->database_name !== $expectedDb) {
            $checks[] = $this->check(
                'target_database',
                'block',
                'Hedef period database_name şirket/yıl adlandırmasıyla uyuşmuyor.',
            );
        } else {
            $checks[] = $this->check(
                'target_database',
                'pass',
                $target ? 'Mevcut hedef period carry uygunluğu kontrol edilecek.' : 'Hedef period carry sırasında oluşturulabilir.',
            );
        }

        if ($target && ($target->carried_at !== null || $target->carried_from_period_id !== null)) {
            $checks[] = $this->check(
                'target_carry_state',
                'block',
                'Hedef dönem daha önce bir carry işlemiyle ilişkilendirilmiş.',
            );
        }

        if ($target && $this->targetHasBusinessData($target)) {
            $checks[] = $this->check(
                'target_business_data',
                'block',
                'Hedef dönem business data içeriyor; otomatik carry başlatılamaz.',
            );
        } elseif ($target) {
            $checks[] = $this->check(
                'target_business_data',
                'pass',
                'Hedef dönem business data içermiyor.',
            );
        }

        $sourceData = PeriodContext::withinSystem(
            $source,
            fn (): array => $this->sourceSummary(),
        );

        $transferCount = $sourceData['blockers']['transfers'];
        $productionCount = $sourceData['blockers']['production_orders'];

        $checks[] = $transferCount > 0
            ? $this->check('in_transit_transfers', 'block', 'Yoldaki/eksik teslim transferler tamamlanmalıdır.', $transferCount)
            : $this->check('in_transit_transfers', 'pass', 'Yoldaki transfer yok.', 0);

        $checks[] = $productionCount > 0
            ? $this->check('open_production', 'block', 'Açık üretim/fason emirleri tamamlanmalı veya iptal edilmelidir.', $productionCount)
            : $this->check('open_production', 'pass', 'Açık üretim/fason emri yok.', 0);

        foreach ([
            ['open_sales_orders', $sourceData['sales_orders'], 'Açık satış siparişleri K-256 ile taşınacak.'],
            ['open_purchase_orders', $sourceData['purchase_orders'], 'Açık satınalma siparişleri K-256 ile taşınacak.'],
            ['open_import_files', $sourceData['import_files'], 'Açık ithalat dosyaları K-259 ile taşınacak.'],
            ['open_quarantine', $sourceData['quarantine'], 'Açık karantina kayıtları yeni döneme taşınacak.'],
        ] as [$key, $items, $message]) {
            $checks[] = $items === []
                ? $this->check($key, 'pass', str_replace(' taşınacak.', ' yok.', $message), 0)
                : $this->check($key, 'warning', $message, count($items));
        }

        $accessCandidates = DB::connection('master')
            ->table('period_user_access')
            ->where('period_id', $source->id)
            ->where('is_active', true)
            ->count();

        $summary = [
            ...$sourceData['summary'],
            'period_access_candidates' => $accessCandidates,
        ];

        return new PeriodCarryPreview(
            sourcePeriodId: (int) $source->id,
            sourceYear: (int) $source->year,
            targetYear: $targetYear,
            targetPeriodId: $target?->id ? (int) $target->id : null,
            canCarry: collect($checks)->doesntContain(fn (array $check): bool => $check['status'] === 'block'),
            checks: $checks,
            summary: $summary,
            salesOrders: $sourceData['sales_orders'],
            purchaseOrders: $sourceData['purchase_orders'],
            importFiles: $sourceData['import_files'],
            quarantine: $sourceData['quarantine'],
        );
    }

    private function assertSourceAccess(Period $source): void
    {
        $userId = auth()->id();

        if ($userId === null) {
            throw new AuthorizationException('Dönem devri önizlemesi oturum açmış kullanıcı gerektirir.');
        }

        $companyAllowed = DB::connection('master')
            ->table('company_user')
            ->where('company_id', $source->company_id)
            ->where('user_id', $userId)
            ->exists();

        $periodAllowed = DB::connection('master')
            ->table('period_user_access')
            ->where('period_id', $source->id)
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->exists();

        if (! $companyAllowed || ! $periodAllowed) {
            throw new AuthorizationException('Kaynak dönem erişimi olmadan carry preview çalıştırılamaz.');
        }
    }

    /** @return array<string,mixed> */
    private function sourceSummary(): array
    {
        PeriodContext::ensure();

        $salesOrders = $this->openOrderSummary(DocumentType::SalesOrder);
        $purchaseOrders = $this->openOrderSummary(DocumentType::PurchaseOrder);
        $imports = DB::connection('period')->table('import_files')
            ->whereIn('status', ['draft', 'in_transit', 'customs'])
            ->whereNull('received_at')
            ->whereNull('closed_at')
            ->orderBy('id')
            ->get()
            ->map(function (object $file): array {
                $containers = DB::connection('period')->table('containers')
                    ->where('import_file_id', $file->id)
                    ->count();
                $packages = DB::connection('period')->table('packages')
                    ->where('import_file_id', $file->id)
                    ->count();
                $costItems = DB::connection('period')->table('import_cost_items')
                    ->where('import_file_id', $file->id)
                    ->count();

                return [
                    'id' => (int) $file->id,
                    'number' => (string) $file->number,
                    'status' => (string) $file->status,
                    'containers' => $containers,
                    'packages' => $packages,
                    'cost_items' => $costItems,
                    'exchange_rate_will_reset' => $file->exchange_rate_locked_at !== null,
                ];
            })
            ->all();

        $quarantine = DB::connection('period')->table('quarantine_entries')
            ->whereIn('status', ['pending', 'partial'])
            ->orderBy('id')
            ->get()
            ->map(fn (object $entry): array => [
                'id' => (int) $entry->id,
                'product_id' => (int) $entry->product_id,
                'location_id' => (int) $entry->location_id,
                'open_quantity' => bcsub(
                    bcsub((string) $entry->quantity, (string) $entry->released_quantity, 3),
                    (string) $entry->scrapped_quantity,
                    3,
                ),
                'unit_cost' => (string) $entry->unit_cost,
            ])
            ->filter(fn (array $entry): bool => bccomp($entry['open_quantity'], '0', 3) > 0)
            ->values()
            ->all();

        return [
            'blockers' => [
                'transfers' => DB::connection('period')->table('transfers')
                    ->whereIn('status', ['in_transit', 'partially_received'])
                    ->count(),
                'production_orders' => DB::connection('period')->table('production_orders')
                    ->whereIn('status', ['draft', 'confirmed', 'in_progress'])
                    ->count(),
            ],
            'sales_orders' => $salesOrders,
            'purchase_orders' => $purchaseOrders,
            'import_files' => $imports,
            'quarantine' => $quarantine,
            'summary' => [
                'stock_quantity' => (string) (DB::connection('period')->table('stock_balances')->sum('quantity') ?? '0'),
                'stock_balance_rows' => DB::connection('period')->table('stock_balances')->count(),
                'product_cost_rows' => DB::connection('period')->table('product_costs')->count(),
                'contact_debit' => (string) (DB::connection('period')->table('contact_transactions')->where('direction', 'debit')->sum('amount') ?? '0'),
                'contact_credit' => (string) (DB::connection('period')->table('contact_transactions')->where('direction', 'credit')->sum('amount') ?? '0'),
                'cash_in' => (string) (DB::connection('period')->table('cash_movements')->where('direction', 'in')->sum('amount') ?? '0'),
                'cash_out' => (string) (DB::connection('period')->table('cash_movements')->where('direction', 'out')->sum('amount') ?? '0'),
                'bank_in' => (string) (DB::connection('period')->table('bank_movements')->where('direction', 'in')->sum('amount') ?? '0'),
                'bank_out' => (string) (DB::connection('period')->table('bank_movements')->where('direction', 'out')->sum('amount') ?? '0'),
                'unmatured_securities' => DB::connection('period')->table('securities')
                    ->whereIn('status', ['portfolio', 'issued', 'endorsed', 'banked'])
                    ->count(),
                'active_recipe_revisions' => DB::connection('period')->table('production_recipes')
                    ->where('is_active', true)
                    ->count(),
                'channel_settings' => DB::connection('period')->table('channel_account_period_settings')->count(),
                'channel_listings' => DB::connection('period')->table('channel_product_listings')->count(),
                'channel_locations' => DB::connection('period')->table('channel_listing_locations')->count(),
            ],
        ];
    }

    /** @return list<array<string,mixed>> */
    private function openOrderSummary(DocumentType $type): array
    {
        $orders = Document::query()
            ->with('lines')
            ->where('document_type', $type->value)
            ->whereIn('status', $type === DocumentType::SalesOrder ? ['confirmed'] : ['approved', 'sent'])
            ->orderBy('id')
            ->get();

        $result = [];

        foreach ($orders as $order) {
            $lines = [];

            foreach ($order->lines as $line) {
                $remaining = $type === DocumentType::SalesOrder
                    ? $this->salesAvailability->orderRemaining($line)
                    : bcsub(
                        $this->purchaseAvailability->orderReceiptRemaining($line),
                        (string) $line->cancelled_quantity,
                        3,
                    );

                if (bccomp($remaining, '0', 3) <= 0) {
                    continue;
                }

                $reservationQuantity = $type === DocumentType::SalesOrder
                    ? (string) (DB::connection('period')->table('stock_reservations')
                        ->where('document_line_id', $line->id)
                        ->where('status', 'active')
                        ->sum('quantity') ?? '0')
                    : '0.000';

                $lines[] = [
                    'line_id' => (int) $line->id,
                    'product_id' => $line->product_id !== null ? (int) $line->product_id : null,
                    'remaining_quantity' => $remaining,
                    'active_reservation_quantity' => $reservationQuantity,
                ];
            }

            if ($lines !== []) {
                $result[] = [
                    'id' => (int) $order->id,
                    'number' => (string) $order->number,
                    'contact_id' => $order->contact_id !== null ? (int) $order->contact_id : null,
                    'lines' => $lines,
                ];
            }
        }

        return $result;
    }

    private function targetHasBusinessData(Period $target): bool
    {
        return (bool) PeriodContext::withinSystem($target, function (): bool {
            foreach ([
                'documents',
                'stock_movements',
                'contact_transactions',
                'cash_movements',
                'bank_movements',
                'securities',
                'transfers',
                'warehouse_slips',
                'stock_counts',
                'import_files',
                'production_orders',
                'channel_sync_events',
            ] as $table) {
                if (Schema::connection('period')->hasTable($table)
                    && DB::connection('period')->table($table)->exists()) {
                    return true;
                }
            }

            return false;
        });
    }

    /** @return array{key:string,status:string,message:string,count?:int} */
    private function check(string $key, string $status, string $message, ?int $count = null): array
    {
        if (! in_array($status, ['pass', 'warning', 'block'], true)) {
            throw new DomainException('Carry preview check status geçersiz.');
        }

        return array_filter([
            'key' => $key,
            'status' => $status,
            'message' => $message,
            'count' => $count,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
