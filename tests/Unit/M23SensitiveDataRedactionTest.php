<?php

namespace Tests\Unit;

use App\Foundation\Logging\SensitiveDataProcessor;
use App\Foundation\Logging\SensitiveDataRedactor;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class M23SensitiveDataRedactionTest extends TestCase
{
    public function test_message_context_and_recovery_secrets_are_redacted(): void
    {
        $processor = new SensitiveDataProcessor(new SensitiveDataRedactor);
        $record = new LogRecord(
            datetime: new DateTimeImmutable('2026-09-03T12:00:00Z'),
            channel: 'test',
            level: Level::Info,
            message: 'Provider failed email=person@example.com Authorization: Bearer abc.def.ghi iban=TR000000000000000000000000 recovery_key=super-secret',
            context: ['private_key' => 'private', 'recovery_key' => 'recovery'],
            extra: ['encryption_key' => 'encryption'],
        );

        $processed = $processor($record);

        self::assertStringNotContainsString('person@example.com', $processed->message);
        self::assertStringNotContainsString('abc.def.ghi', $processed->message);
        self::assertStringNotContainsString('TR000000000000000000000000', $processed->message);
        self::assertStringNotContainsString('super-secret', $processed->message);
        self::assertSame(SensitiveDataRedactor::REDACTED, $processed->context['private_key']);
        self::assertSame(SensitiveDataRedactor::REDACTED, $processed->context['recovery_key']);
        self::assertSame(SensitiveDataRedactor::REDACTED, $processed->extra['encryption_key']);
    }
}
