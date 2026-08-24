<?php

namespace Tests\Unit;

use App\Foundation\Logging\SensitiveDataProcessor;
use App\Foundation\Logging\SensitiveDataRedactor;
use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class SensitiveDataProcessorTest extends TestCase
{
    public function test_sensitive_context_and_pii_are_redacted(): void
    {
        $processor = new SensitiveDataProcessor(new SensitiveDataRedactor());
        $record = new LogRecord(
            datetime: new DateTimeImmutable('2026-08-24T12:00:00Z'),
            channel: 'test',
            level: Level::Info,
            message: 'test',
            context: [
                'document_id' => 'INV-1',
                'password' => 'plain-password',
                'customer' => [
                    'email' => 'person@example.com',
                    'note' => 'Authorization: Bearer abc.def.ghi',
                ],
            ],
            extra: ['iban' => 'TR000000000000000000000000'],
        );

        $processed = $processor($record);

        self::assertSame('INV-1', $processed->context['document_id']);
        self::assertSame(SensitiveDataRedactor::REDACTED, $processed->context['password']);
        self::assertSame(SensitiveDataRedactor::REDACTED, $processed->context['customer']['email']);
        self::assertSame('Authorization: Bearer [REDACTED]', $processed->context['customer']['note']);
        self::assertSame(SensitiveDataRedactor::REDACTED, $processed->extra['iban']);
    }
}
