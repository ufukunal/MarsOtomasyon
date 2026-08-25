<?php

namespace App\Modules\Accounts\Ledger;

use App\Modules\Accounts\Models\Account;
use App\Modules\Core\Company\ActiveCompanyContext;
use DateTimeImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;

final readonly class AccountLedgerReader
{
    public function __construct(private ActiveCompanyContext $companyContext) {}

    public function balance(Account $account): AccountBalance
    {
        $this->assertAccountScope($account);

        return $this->balanceThrough($account, null);
    }

    /**
     * @param  Collection<int, Account>  $accounts
     * @return array<int, AccountBalance>
     */
    public function balances(Collection $accounts): array
    {
        if ($accounts->isEmpty()) {
            return [];
        }

        $companyId = $this->companyId();
        $currencyByAccount = [];

        foreach ($accounts as $account) {
            $this->assertAccountScope($account);
            $accountId = $account->getKey();
            if (! is_int($accountId)) {
                throw new LogicException('Cari bakiye okuması persisted cari gerektirir.');
            }

            $currencyByAccount[$accountId] = (string) $account->book_currency_code;
        }

        $rows = DB::table('account_transactions')
            ->select('account_id')
            ->selectRaw('SUM(signed_amount)::text AS signed_balance')
            ->where('company_id', $companyId)
            ->whereIn('account_id', array_keys($currencyByAccount))
            ->groupBy('account_id')
            ->get();

        $balances = [];
        foreach ($currencyByAccount as $accountId => $currencyCode) {
            $balances[$accountId] = new AccountBalance('0', $currencyCode);
        }

        foreach ($rows as $row) {
            $data = (array) $row;
            $accountId = (int) ($data['account_id'] ?? 0);
            if (! isset($currencyByAccount[$accountId])) {
                continue;
            }

            $balances[$accountId] = new AccountBalance(
                (string) ($data['signed_balance'] ?? '0'),
                $currencyByAccount[$accountId],
            );
        }

        return $balances;
    }

    public function statement(Account $account, ?string $from, ?string $to): AccountStatement
    {
        $this->assertAccountScope($account);
        $this->assertDateRange($from, $to);

        $accountId = $account->getKey();
        if (! is_int($accountId)) {
            throw new LogicException('Cari ekstresi persisted cari gerektirir.');
        }

        $companyId = $this->companyId();
        $currencyCode = (string) $account->book_currency_code;
        $opening = $from === null
            ? new AccountBalance('0', $currencyCode)
            : $this->balanceBefore($account, $from);
        $closing = $this->balanceThrough($account, $to);

        $periodRows = DB::table('account_transactions')
            ->select(['id', 'posting_date', 'signed_amount', 'source_type', 'memo'])
            ->selectRaw('SUM(signed_amount) OVER (ORDER BY posting_date, id ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW)::text AS period_running_balance')
            ->where('company_id', $companyId)
            ->where('account_id', $accountId)
            ->when($from !== null, fn ($query) => $query->where('posting_date', '>=', $from))
            ->when($to !== null, fn ($query) => $query->where('posting_date', '<=', $to));

        $paginator = DB::query()
            ->fromSub($periodRows, 'account_statement_rows')
            ->select(['id', 'posting_date', 'signed_amount', 'source_type', 'memo'])
            ->selectRaw('(?::numeric + period_running_balance::numeric)::text AS running_balance', [$opening->signedAmount])
            ->orderBy('posting_date')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString();

        $paginator->setCollection($paginator->getCollection()->map(function (object $row) use ($currencyCode): array {
            $data = (array) $row;

            return [
                'posting_date' => (string) ($data['posting_date'] ?? ''),
                'description' => $this->sourceLabel((string) ($data['source_type'] ?? '')),
                'memo' => ($data['memo'] ?? null) === null ? null : (string) $data['memo'],
                'movement' => new AccountBalance((string) ($data['signed_amount'] ?? '0'), $currencyCode),
                'running_balance' => new AccountBalance((string) ($data['running_balance'] ?? '0'), $currencyCode),
            ];
        }));

        /** @var LengthAwarePaginator<int, array{posting_date:string,description:string,memo:?string,movement:AccountBalance,running_balance:AccountBalance}> $paginator */
        return new AccountStatement($opening, $closing, $paginator);
    }

    private function balanceBefore(Account $account, string $date): AccountBalance
    {
        return $this->aggregateBalance($account, static fn ($query) => $query->where('posting_date', '<', $date));
    }

    private function balanceThrough(Account $account, ?string $date): AccountBalance
    {
        return $this->aggregateBalance(
            $account,
            static fn ($query) => $date === null ? $query : $query->where('posting_date', '<=', $date),
        );
    }

    private function aggregateBalance(Account $account, callable $scope): AccountBalance
    {
        $accountId = $account->getKey();
        if (! is_int($accountId)) {
            throw new LogicException('Cari bakiye okuması persisted cari gerektirir.');
        }

        $query = DB::table('account_transactions')
            ->where('company_id', $this->companyId())
            ->where('account_id', $accountId);
        $scope($query);

        $row = $query
            ->selectRaw('COALESCE(SUM(signed_amount), 0)::text AS signed_balance')
            ->first();
        $data = $row === null ? [] : (array) $row;

        return new AccountBalance(
            (string) ($data['signed_balance'] ?? '0'),
            (string) $account->book_currency_code,
        );
    }

    private function sourceLabel(string $sourceType): string
    {
        return match ($sourceType) {
            'sales.invoice' => 'Satış Faturası',
            'sales.return' => 'Satış İadesi',
            'purchase.invoice', 'purchase.supplier-invoice' => 'Alış Faturası',
            'purchase.return' => 'Alış İadesi',
            'treasury.collection' => 'Tahsilat',
            'treasury.payment' => 'Ödeme',
            'instrument.received' => 'Alınan Çek / Senet',
            'instrument.issued' => 'Verilen Çek / Senet',
            default => 'Cari Hareketi',
        };
    }

    private function assertDateRange(?string $from, ?string $to): void
    {
        foreach ([$from, $to] as $date) {
            if ($date === null) {
                continue;
            }

            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
            if ($parsed === false || $parsed->format('Y-m-d') !== $date) {
                throw new InvalidArgumentException('Cari ekstre tarih filtresi geçersizdir.');
            }
        }

        if ($from !== null && $to !== null && $from > $to) {
            throw new InvalidArgumentException('Cari ekstre başlangıç tarihi bitiş tarihinden sonra olamaz.');
        }
    }

    private function assertAccountScope(Account $account): void
    {
        if ((int) $account->company_id !== $this->companyId()) {
            throw new LogicException('Cari ledger okuması aktif şirket sınırı dışında çalışamaz.');
        }
    }

    private function companyId(): int
    {
        $companyId = $this->companyContext->requireCompany()->getKey();
        if (! is_int($companyId)) {
            throw new LogicException('Cari ledger okuması persisted aktif şirket gerektirir.');
        }

        return $companyId;
    }
}
