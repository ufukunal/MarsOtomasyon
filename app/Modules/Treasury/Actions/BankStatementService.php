<?php

namespace App\Modules\Treasury\Actions;

use App\Foundation\Clock\Clock;
use App\Modules\Treasury\Import\BankStatementParser;
use App\Modules\Treasury\Models\TreasuryAccount;
use DomainException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final readonly class BankStatementService
{
    public function __construct(private BankStatementParser $parser, private Clock $clock) {}

    public function import(int $companyId, int $treasuryAccountId, string $format, UploadedFile $file): int
    {
        return DB::transaction(function () use ($companyId, $treasuryAccountId, $format, $file): int {
            $account = TreasuryAccount::query()->where('company_id', $companyId)->whereKey($treasuryAccountId)->sharedLock()->firstOrFail();
            if ((string) $account->type !== 'bank') throw new DomainException('Banka ekstresi yalnız bank treasury hesabına aktarılabilir.');
            $path = $file->getRealPath();
            if ($path === false) throw new DomainException('Yüklenen ekstre dosyasına erişilemedi.');
            $hash = hash_file('sha256', $path);
            if (! is_string($hash)) throw new DomainException('Ekstre dosyası hash hesaplaması başarısız.');
            $existing = DB::table('bank_statement_imports')->where('company_id', $companyId)->where('treasury_account_id', $treasuryAccountId)->where('file_hash', $hash)->value('id');
            if ($existing !== null) return (int) $existing;

            $rows = $this->parser->parse($format, $path, (string) $account->currency_code);
            if ($rows === []) throw new DomainException('Ekstre içinde aktarılabilir hareket bulunamadı.');
            foreach ($rows as $row) {
                if ($row['currency_code'] !== (string) $account->currency_code) throw new DomainException('V1 banka ekstresi treasury hesabıyla aynı para biriminde olmalıdır.');
            }

            $importId = (int) DB::table('bank_statement_imports')->insertGetId([
                'company_id' => $companyId,
                'treasury_account_id' => $treasuryAccountId,
                'format' => $format,
                'file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                'file_hash' => $hash,
                'line_count' => count($rows),
                'created_at' => $this->clock->now(),
            ]);
            foreach ($rows as $row) {
                DB::table('bank_statement_lines')->insert([
                    'company_id' => $companyId,
                    'bank_statement_import_id' => $importId,
                    'treasury_account_id' => $treasuryAccountId,
                    'external_key' => $row['external_key'],
                    'booking_date' => $row['booking_date'],
                    'value_date' => $row['value_date'],
                    'currency_code' => $row['currency_code'],
                    'signed_amount' => $row['signed_amount'],
                    'reference' => $row['reference'],
                    'description' => $row['description'],
                    'match_status' => 'unmatched',
                    'created_at' => $this->clock->now(),
                    'updated_at' => $this->clock->now(),
                ]);
            }
            return $importId;
        }, 3);
    }

    public function match(int $companyId, int $lineId, int $movementId): void
    {
        DB::transaction(function () use ($companyId, $lineId, $movementId): void {
            $line = DB::table('bank_statement_lines')->where('company_id', $companyId)->where('id', $lineId)->lockForUpdate()->first();
            if ($line === null) throw new DomainException('Ekstre satırı bulunamadı.');
            if ((string) $line->match_status === 'matched' && (int) $line->matched_treasury_movement_id === $movementId) return;
            if ((string) $line->match_status !== 'unmatched') throw new DomainException('Yalnız eşleşmemiş ekstre satırı eşleştirilebilir.');
            DB::table('bank_statement_lines')->where('id', $lineId)->update([
                'match_status' => 'matched', 'matched_treasury_movement_id' => $movementId,
                'matched_at' => $this->clock->now(), 'updated_at' => $this->clock->now(),
            ]);
        }, 3);
    }

    public function ignore(int $companyId, int $lineId): void
    {
        $updated = DB::table('bank_statement_lines')->where('company_id', $companyId)->where('id', $lineId)->where('match_status', 'unmatched')->update([
            'match_status' => 'ignored', 'updated_at' => $this->clock->now(),
        ]);
        if ($updated !== 1) throw new DomainException('Yalnız eşleşmemiş ekstre satırı yok sayılabilir.');
    }
}
