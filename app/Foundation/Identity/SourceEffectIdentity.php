<?php

namespace App\Foundation\Identity;

use InvalidArgumentException;
use JsonException;
use Stringable;

final readonly class SourceEffectIdentity implements Stringable
{
    public function __construct(
        public int $companyId,
        public string $sourceType,
        public string $sourceId,
        public string $effectType,
    ) {
        if ($companyId < 1) {
            throw new InvalidArgumentException('Source effect identity requires a persisted company id.');
        }

        self::assertCanonicalKey($sourceType, 'Source type');
        self::assertCanonicalKey($effectType, 'Effect type');

        if ($sourceId === '' || $sourceId !== trim($sourceId) || mb_strlen($sourceId) > 255) {
            throw new InvalidArgumentException('Source id must be non-empty, unpadded and at most 255 characters.');
        }
    }

    /** @return array{company_id:int,source_type:string,source_id:string,effect_type:string} */
    public function components(): array
    {
        return [
            'company_id' => $this->companyId,
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'effect_type' => $this->effectType,
        ];
    }

    /**
     * Stable opaque effect identity for exactly-once guards.
     *
     * @throws JsonException
     */
    public function fingerprint(): string
    {
        return hash('sha256', json_encode($this->components(), JSON_THROW_ON_ERROR));
    }

    /** @throws JsonException */
    public function __toString(): string
    {
        return $this->fingerprint();
    }

    private static function assertCanonicalKey(string $value, string $label): void
    {
        if (! preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', $value)) {
            throw new InvalidArgumentException($label.' must be canonical lowercase ASCII.');
        }
    }
}
