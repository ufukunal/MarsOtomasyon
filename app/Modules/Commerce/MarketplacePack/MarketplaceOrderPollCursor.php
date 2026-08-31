<?php

namespace App\Modules\Commerce\MarketplacePack;

use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class MarketplaceOrderPollCursor
{
    /**
     * @param  object{order_poll_watermark_at?:mixed,order_poll_cursor?:mixed}  $connection
     * @return array{automatic:bool,start:string,end:string,page:int,pagination_token:?string}
     */
    public function resolve(object $connection, ?string $modifiedAfter, int $requestedPage): array
    {
        if ($modifiedAfter !== null && trim($modifiedAfter) !== '') {
            return [
                'automatic' => false,
                'start' => $this->timestamp($modifiedAfter, 'Marketplace polling modified-after value is invalid.'),
                'end' => CarbonImmutable::now()->toIso8601String(),
                'page' => $requestedPage,
                'pagination_token' => null,
            ];
        }

        $cursor = $this->decodeCursor($connection->order_poll_cursor ?? null);
        if ($cursor !== null) {
            return [
                'automatic' => true,
                'start' => $cursor['start'],
                'end' => $cursor['end'],
                'page' => $cursor['page'],
                'pagination_token' => $cursor['pagination_token'],
            ];
        }

        $watermark = $connection->order_poll_watermark_at ?? null;
        $start = $watermark === null
            ? CarbonImmutable::now()->subDays(7)->toIso8601String()
            : $this->timestamp((string) $watermark, 'Marketplace order polling watermark is invalid.');

        return [
            'automatic' => true,
            'start' => $start,
            'end' => CarbonImmutable::now()->toIso8601String(),
            'page' => $requestedPage,
            'pagination_token' => null,
        ];
    }

    /**
     * @param  object{id:mixed}  $connection
     * @param  array{automatic:bool,start:string,end:string,page:int,pagination_token:?string}  $window
     */
    public function advance(
        object $connection,
        string $provider,
        array $window,
        mixed $body,
        int $recordCount,
        int $perPage,
    ): void {
        if (! $window['automatic']) {
            return;
        }

        $next = $this->nextCursor($provider, $window, $body, $recordCount, $perPage);
        $updates = [
            'last_sync_at' => now(),
            'last_success_at' => now(),
            'last_error' => null,
            'updated_at' => now(),
        ];

        if ($next === null) {
            $updates['order_poll_cursor'] = null;
            $updates['order_poll_watermark_at'] = CarbonImmutable::parse($window['end']);
        } else {
            $updates['order_poll_cursor'] = json_encode($next, JSON_THROW_ON_ERROR);
        }

        DB::table('integration_connections')->where('id', $connection->id)->update($updates);
    }

    /**
     * @param  array{automatic:bool,start:string,end:string,page:int,pagination_token:?string}  $window
     * @return array{version:int,start:string,end:string,page:int,pagination_token:?string}|null
     */
    private function nextCursor(string $provider, array $window, mixed $body, int $recordCount, int $perPage): ?array
    {
        if ($provider === 'amazon') {
            $token = null;
            if (is_array($body)) {
                $candidate = $body['pagination']['nextToken'] ?? null;
                if (is_scalar($candidate) && trim((string) $candidate) !== '') {
                    $token = trim((string) $candidate);
                }
            }

            return $token === null ? null : [
                'version' => 1,
                'start' => $window['start'],
                'end' => $window['end'],
                'page' => 1,
                'pagination_token' => $token,
            ];
        }

        if (! in_array($provider, ['hepsiburada', 'n11', 'idefix'], true)) {
            return null;
        }

        $totalPages = $this->totalPages($body);
        $hasMore = $totalPages === null
            ? $recordCount >= $perPage
            : $window['page'] < $totalPages;

        if (! $hasMore) {
            return null;
        }

        return [
            'version' => 1,
            'start' => $window['start'],
            'end' => $window['end'],
            'page' => $window['page'] + 1,
            'pagination_token' => null,
        ];
    }

    private function totalPages(mixed $body): ?int
    {
        if (! is_array($body)) {
            return null;
        }
        foreach (['totalPages', 'totalPage'] as $key) {
            if (isset($body[$key]) && is_numeric($body[$key])) {
                return max(0, (int) $body[$key]);
            }
        }

        return null;
    }

    /** @return array{version:int,start:string,end:string,page:int,pagination_token:?string}|null */
    private function decodeCursor(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }
        $decoded = is_array($value) ? $value : json_decode((string) $value, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Marketplace order polling cursor is invalid JSON.');
        }
        $version = (int) ($decoded['version'] ?? 0);
        $page = (int) ($decoded['page'] ?? 0);
        $start = isset($decoded['start']) && is_scalar($decoded['start']) ? trim((string) $decoded['start']) : '';
        $end = isset($decoded['end']) && is_scalar($decoded['end']) ? trim((string) $decoded['end']) : '';
        $token = isset($decoded['pagination_token']) && is_scalar($decoded['pagination_token'])
            ? trim((string) $decoded['pagination_token'])
            : null;
        if ($version !== 1 || $page < 1 || $start === '' || $end === '') {
            throw new RuntimeException('Marketplace order polling cursor shape is invalid.');
        }
        $start = $this->timestamp($start, 'Marketplace order polling cursor start is invalid.');
        $end = $this->timestamp($end, 'Marketplace order polling cursor end is invalid.');
        if (CarbonImmutable::parse($start)->greaterThan(CarbonImmutable::parse($end))) {
            throw new RuntimeException('Marketplace order polling cursor window is invalid.');
        }

        return [
            'version' => 1,
            'start' => $start,
            'end' => $end,
            'page' => $page,
            'pagination_token' => $token === '' ? null : $token,
        ];
    }

    private function timestamp(string $value, string $message): string
    {
        try {
            return CarbonImmutable::parse(trim($value))->toIso8601String();
        } catch (\Throwable) {
            throw new DomainException($message);
        }
    }
}
