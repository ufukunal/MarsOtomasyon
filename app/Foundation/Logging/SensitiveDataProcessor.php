<?php

namespace App\Foundation\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

final readonly class SensitiveDataProcessor implements ProcessorInterface
{
    public function __construct(private SensitiveDataRedactor $redactor) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->redactor->redactText($record->message),
            context: $this->redactor->redact($record->context),
            extra: $this->redactor->redact($record->extra),
        );
    }
}
