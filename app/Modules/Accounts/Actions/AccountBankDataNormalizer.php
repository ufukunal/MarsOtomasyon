<?php

namespace App\Modules\Accounts\Actions;

use App\Modules\Core\Models\Currency;
use Illuminate\Validation\ValidationException;

final readonly class AccountBankDataNormalizer
{
    /** @param array<string, mixed> $row
     * @return array{bank_name:string,branch_name:?string,account_holder:?string,iban:?string,account_number:?string,swift_code:?string,currency_code:string,is_default:bool,note:?string}
     */
    public function normalize(array $row, int $index): array
    {
        $iban = $this->iban($row['iban'] ?? null, "bank_accounts.$index.iban");
        $accountNumber = $this->compact($row['account_number'] ?? null, 64, "bank_accounts.$index.account_number");
        if ($iban === null && $accountNumber === null) {
            throw ValidationException::withMessages(["bank_accounts.$index.iban" => 'IBAN veya hesap numarasından en az biri girilmelidir.']);
        }

        return [
            'bank_name' => $this->required($row['bank_name'] ?? null, 160, "bank_accounts.$index.bank_name", 'Banka adı zorunludur.'),
            'branch_name' => $this->optional($row['branch_name'] ?? null, 120, "bank_accounts.$index.branch_name"),
            'account_holder' => $this->optional($row['account_holder'] ?? null, 200, "bank_accounts.$index.account_holder"),
            'iban' => $iban,
            'account_number' => $accountNumber,
            'swift_code' => $this->swift($row['swift_code'] ?? null, "bank_accounts.$index.swift_code"),
            'currency_code' => $this->currency($row['currency_code'] ?? null, "bank_accounts.$index.currency_code"),
            'is_default' => (bool) ($row['is_default'] ?? false),
            'note' => $this->optional($row['note'] ?? null, 500, "bank_accounts.$index.note"),
        ];
    }

    public function normalizeIban(mixed $raw, string $field): ?string
    {
        return $this->iban($raw, $field);
    }

    private function required(mixed $raw, int $max, string $field, string $message): string
    {
        $value = trim(is_string($raw) ? $raw : '');
        if ($value === '' || mb_strlen($value) > $max) {
            throw ValidationException::withMessages([$field => $message]);
        }

        return $value;
    }

    private function optional(mixed $raw, int $max, string $field): ?string
    {
        $value = trim(is_string($raw) ? $raw : '');
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $max) {
            throw ValidationException::withMessages([$field => 'Alan izin verilen uzunluğu aşıyor.']);
        }

        return $value;
    }

    private function compact(mixed $raw, int $max, string $field): ?string
    {
        $value = preg_replace('/\s+/u', '', is_string($raw) ? $raw : '');
        if (! is_string($value) || $value === '') {
            return null;
        }
        $value = mb_strtoupper($value);
        if (mb_strlen($value) > $max || preg_match('/^[A-Z0-9._\/-]+$/', $value) !== 1) {
            throw ValidationException::withMessages([$field => 'Hesap numarası geçersizdir.']);
        }

        return $value;
    }

    private function iban(mixed $raw, string $field): ?string
    {
        $iban = preg_replace('/\s+/u', '', is_string($raw) ? $raw : '');
        if (! is_string($iban) || $iban === '') {
            return null;
        }
        $iban = mb_strtoupper($iban);
        if (preg_match('/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/', $iban) !== 1 || ! $this->validIbanChecksum($iban)) {
            throw ValidationException::withMessages([$field => 'IBAN biçimi veya checksum değeri geçersizdir.']);
        }

        return $iban;
    }

    private function validIbanChecksum(string $iban): bool
    {
        $value = substr($iban, 4).substr($iban, 0, 4);
        $remainder = 0;

        foreach (str_split($value) as $character) {
            $digits = ctype_alpha($character) ? (string) (ord($character) - 55) : $character;
            foreach (str_split($digits) as $digit) {
                $remainder = (($remainder * 10) + (int) $digit) % 97;
            }
        }

        return $remainder === 1;
    }

    private function swift(mixed $raw, string $field): ?string
    {
        $swift = preg_replace('/\s+/u', '', is_string($raw) ? $raw : '');
        if (! is_string($swift) || $swift === '') {
            return null;
        }
        $swift = mb_strtoupper($swift);
        if (preg_match('/^[A-Z0-9]{8}([A-Z0-9]{3})?$/', $swift) !== 1) {
            throw ValidationException::withMessages([$field => 'SWIFT/BIC kodu 8 veya 11 karakter olmalıdır.']);
        }

        return $swift;
    }

    private function currency(mixed $raw, string $field): string
    {
        $code = mb_strtoupper(trim(is_string($raw) ? $raw : ''));
        if (preg_match('/^[A-Z]{3}$/', $code) !== 1 || ! Currency::query()->whereKey($code)->exists()) {
            throw ValidationException::withMessages([$field => 'Geçerli bir para birimi seçilmelidir.']);
        }

        return $code;
    }
}
