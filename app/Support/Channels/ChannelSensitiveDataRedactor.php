<?php

namespace App\Support\Channels;

use App\Models\SalesChannelAccount;

final class ChannelSensitiveDataRedactor
{
    public function redact(string $text, SalesChannelAccount $account): string
    {
        $secrets = $this->flattenSecrets($account->credentials());

        foreach ($secrets as $secret) {
            if ($secret !== '' && mb_strlen($secret) >= 4) {
                $text = str_replace($secret, '[REDACTED]', $text);
            }
        }

        $text = preg_replace(
            '/(?i)(authorization|api[-_ ]?key|secret|token|password)\s*[:=]\s*[^\s,;]+/',
            '$1=[REDACTED]',
            $text,
        ) ?? $text;

        return mb_substr($text, 0, 2000);
    }

    /** @return list<string> */
    private function flattenSecrets(array $credentials): array
    {
        $values = [];

        array_walk_recursive($credentials, function (mixed $value) use (&$values): void {
            if (is_scalar($value) && $value !== null) {
                $values[] = (string) $value;
            }
        });

        return array_values(array_unique($values));
    }
}
