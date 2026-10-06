<?php

namespace App\Actions\Finance;

use App\Actions\Periods\EnsurePeriodOpen;
use App\Models\Period\BankAccount;
use App\Models\Period\BankMovement;
use App\Support\Audit\AuditContext;
use App\Support\Auth\MutationAuthorizer;
use App\Support\Concurrency\IdempotencyKey;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Ekstre satırları defter hareketi değildir. origin=statement olarak saklanır.
 */
final class ImportBankStatement
{
    public function __construct(
        private readonly ParseBankStatementFile $parser,
        private readonly EnsurePeriodOpen $ensurePeriodOpen,
    ) {}

    /** @return array{imported:int,duplicates:int,rows:list<int>,checksum:string} */
    public function handle(
        int $bankAccountId,
        string $path,
        string $format,
        string $idempotencyKey,
    ): array {
        MutationAuthorizer::authorize('bank_statements.create');

        return IdempotencyKey::run(
            $idempotencyKey,
            'bank-statement.import:'.$bankAccountId,
            function () use ($bankAccountId, $path, $format): array {
                $account = BankAccount::query()
                    ->where('is_active', true)
                    ->findOrFail($bankAccountId);
                $rows = $this->parser->handle($path, $format);
                $checksum = hash_file('sha256', $path);

                if ($checksum === false) {
                    throw new DomainException('Ekstre dosyası checksum üretilemedi.');
                }

                $imported = 0;
                $duplicates = 0;
                $ids = [];

                DB::connection('period')->transaction(function () use (
                    $account,
                    $rows,
                    $checksum,
                    $format,
                    &$imported,
                    &$duplicates,
                    &$ids,
                ): void {
                    $actor = auth()->user();

                    foreach ($rows as $row) {
                        $this->ensurePeriodOpen->handle(CarbonImmutable::parse($row['date'], config('app.timezone')));
                        $fingerprint = hash('sha256', implode('|', [
                            (string) $account->id,
                            $row['date'],
                            (string) ($row['value_date'] ?? ''),
                            (string) ($row['reference'] ?? ''),
                            $row['description'],
                            $row['direction'],
                            $row['amount'],
                            (string) ($row['balance'] ?? ''),
                        ]));

                        $existing = BankMovement::query()
                            ->where('bank_account_id', $account->id)
                            ->where('statement_fingerprint', $fingerprint)
                            ->first();

                        if ($existing) {
                            $duplicates++;

                            continue;
                        }

                        $movement = BankMovement::query()->create([
                            'bank_account_id' => $account->id,
                            'movement_date' => $row['date'],
                            'direction' => $row['direction'],
                            'movement_type' => 'statement',
                            'amount' => $row['amount'],
                            'origin' => 'statement',
                            'reference' => $row['reference'],
                            'statement_fingerprint' => $fingerprint,
                            'statement_value_date' => $row['value_date'],
                            'statement_description' => $row['description'],
                            'statement_balance' => $row['balance'],
                            'imported_at' => now(),
                            'description' => $row['description'],
                            'metadata' => [
                                'file_checksum' => $checksum,
                                'import_format' => $format,
                            ],
                            'created_by' => $actor?->id,
                            'created_by_name' => $actor?->name,
                        ]);

                        $ids[] = (int) $movement->id;
                        $imported++;
                    }
                }, attempts: 3);

                AuditContext::period(
                    'Banka ekstresi içe aktarıldı.',
                    [
                        'bank_account_id' => $account->id,
                        'checksum' => $checksum,
                        'imported' => $imported,
                        'duplicates' => $duplicates,
                    ],
                    $account,
                    'bank_statement_imported',
                );

                return [
                    'imported' => $imported,
                    'duplicates' => $duplicates,
                    'rows' => $ids,
                    'checksum' => $checksum,
                ];
            },
        );
    }
}
