<?php

namespace App\Modules\Accounts\Identity;

use App\Modules\Accounts\Enums\TaxIdentityType;
use InvalidArgumentException;

final class TaxIdentity
{
    public static function normalize(TaxIdentityType $type, ?string $raw): ?string
    {
        $value = trim((string) $raw);

        if ($type === TaxIdentityType::None) {
            if ($value !== '') {
                throw new InvalidArgumentException('Vergi kimliği tipi yok ise numara girilemez.');
            }

            return null;
        }

        if ($value === '') {
            throw new InvalidArgumentException('Seçilen vergi kimliği tipi için numara zorunludur.');
        }

        if ($type === TaxIdentityType::Foreign) {
            $value = mb_strtoupper($value);

            if (mb_strlen($value) > 32 || preg_match('/^[A-Z0-9][A-Z0-9 ._\/-]{0,31}$/', $value) !== 1) {
                throw new InvalidArgumentException('Yabancı vergi kimliği geçersiz biçimde.');
            }

            return $value;
        }

        if (preg_match('/^\d+$/', $value) !== 1) {
            throw new InvalidArgumentException('VKN/TCKN yalnız rakamlardan oluşmalıdır.');
        }

        if ($type === TaxIdentityType::Tckn && ! self::validTckn($value)) {
            throw new InvalidArgumentException('TCKN checksum doğrulaması başarısız.');
        }

        if ($type === TaxIdentityType::Vkn && ! self::validVkn($value)) {
            throw new InvalidArgumentException('VKN checksum doğrulaması başarısız.');
        }

        return $value;
    }

    public static function validTckn(string $value): bool
    {
        if (preg_match('/^[1-9]\d{10}$/', $value) !== 1) {
            return false;
        }

        $digits = array_map('intval', str_split($value));
        $odd = $digits[0] + $digits[2] + $digits[4] + $digits[6] + $digits[8];
        $even = $digits[1] + $digits[3] + $digits[5] + $digits[7];
        $tenth = (($odd * 7) - $even) % 10;
        if ($tenth < 0) {
            $tenth += 10;
        }

        if ($digits[9] !== $tenth) {
            return false;
        }

        return $digits[10] === (array_sum(array_slice($digits, 0, 10)) % 10);
    }

    public static function validVkn(string $value): bool
    {
        if (preg_match('/^\d{10}$/', $value) !== 1) {
            return false;
        }

        $digits = array_map('intval', str_split($value));
        $sum = 0;

        for ($index = 0; $index < 9; $index++) {
            $position = 9 - $index;
            $adjusted = ($digits[$index] + $position) % 10;
            $weighted = ($adjusted * (2 ** $position)) % 9;

            if ($adjusted !== 0 && $weighted === 0) {
                $weighted = 9;
            }

            $sum += $weighted;
        }

        return $digits[9] === ((10 - ($sum % 10)) % 10);
    }
}
