<?php

namespace App\Foundation\Outbox;

use InvalidArgumentException;

final readonly class OutboxMessageDraft
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $effectKey,
        public string $eventName,
        public array $payload,
        public string $correlationId,
        public ?int $companyId = null,
        public ?string $sourceType = null,
        public ?string $sourceId = null,
        public ?int $sourceVersion = null,
    ) {
        self::assertCanonicalKey($effectKey, 180, 'Outbox effect key is invalid.');
        self::assertCanonicalKey($correlationId, 64, 'Outbox correlation ID is invalid.');

        if ($companyId !== null && $companyId < 1) {
            throw new InvalidArgumentException('Outbox company ID must be positive when present.');
        }

        if (($sourceType === null) !== ($sourceId === null)) {
            throw new InvalidArgumentException('Outbox source type and source ID must be provided together.');
        }

        if ($sourceType !== null) {
            if (preg_match('/^[a-z][a-z0-9]*(?:[._:-][a-z0-9]+)*$/D', $sourceType) !== 1 || strlen($sourceType) > 100) {
                throw new InvalidArgumentException('Outbox source type is not canonical.');
            }

            self::assertCanonicalKey((string) $sourceId, 128, 'Outbox source ID is invalid.');
        }

        if ($sourceVersion !== null && ($sourceType === null || $sourceVersion < 1)) {
            throw new InvalidArgumentException('Outbox source version requires a source and must be positive.');
        }
    }

    private static function assertCanonicalKey(string $value, int $maxLength, string $message): void
    {
        if ($value === '' || strlen($value) > $maxLength || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]*$/D', $value) !== 1) {
            throw new InvalidArgumentException($message);
        }
    }
}
