<?php

namespace App\Support\Operations;

use Throwable;

final class OperationalErrorSanitizer
{
    public function summarize(Throwable|string $error): string
    {
        $message = $error instanceof Throwable ? $error->getMessage() : $error;
        $message = trim(preg_replace('/\s+/', ' ', $message) ?? '');

        foreach ([
            '/(authorization\s*[:=]\s*)([^\s,;]+)/i',
            '/((?:password|secret|token|credential|api[_-]?key|access[_-]?key|private[_-]?key|consumer[_-]?secret)\s*[:=]\s*)([^\s,;]+)/i',
            '/("?(?:password|secret|token|credential|api_key|access_key|private_key|consumer_secret)"?\s*:\s*")[^"]*(")/i',
            '#(https?://[^:/\s]+:)[^@/\s]+@#i',
        ] as $index => $pattern) {
            $replacement = $index === 2 ? '$1[REDACTED]$2' : ($index === 3 ? '$1[REDACTED]@' : '$1[REDACTED]');
            $message = preg_replace($pattern, $replacement, $message) ?? $message;
        }

        if ($message === '') {
            $message = $error instanceof Throwable ? class_basename($error) : 'Operational failure.';
        }

        return mb_substr($message, 0, 500);
    }
}
