<?php

namespace App\Foundation\Outbox;

use InvalidArgumentException;

final readonly class OutboxEventDefinition
{
    /**
     * @param  list<string>  $allowedPayloadKeys
     * @param  list<string>  $requiredPayloadKeys
     */
    public function __construct(
        public string $name,
        public int $schemaVersion,
        public OutboxSemantic $semantic,
        public OutboxRetryCapability $retryCapability,
        public array $allowedPayloadKeys,
        public array $requiredPayloadKeys,
    ) {
        if ($schemaVersion < 1) {
            throw new InvalidArgumentException('Outbox schema version must be positive.');
        }

        if (preg_match('/^[a-z][a-z0-9]*(?:\.[a-z0-9_]+)+\.v[1-9][0-9]*$/D', $name) !== 1) {
            throw new InvalidArgumentException('Outbox event name is not canonical.');
        }

        if (! str_ends_with($name, '.v'.$schemaVersion)) {
            throw new InvalidArgumentException('Outbox event name version and schema version must match.');
        }
    }
}
