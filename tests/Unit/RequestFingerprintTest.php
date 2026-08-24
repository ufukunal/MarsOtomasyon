<?php

namespace Tests\Unit;

use App\Foundation\Idempotency\RequestFingerprint;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RequestFingerprintTest extends TestCase
{
    public function test_associative_key_order_does_not_change_fingerprint(): void
    {
        $left = RequestFingerprint::fromPayload(['b' => 2, 'a' => ['y' => 2, 'x' => 1]]);
        $right = RequestFingerprint::fromPayload(['a' => ['x' => 1, 'y' => 2], 'b' => 2]);

        self::assertSame($left->value, $right->value);
    }

    public function test_list_order_changes_fingerprint(): void
    {
        self::assertNotSame(
            RequestFingerprint::fromPayload(['items' => [1, 2]])->value,
            RequestFingerprint::fromPayload(['items' => [2, 1]])->value,
        );
    }

    public function test_non_json_payload_value_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RequestFingerprint::fromPayload(['invalid' => new \stdClass]);
    }
}
