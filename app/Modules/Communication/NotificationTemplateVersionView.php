<?php

namespace App\Modules\Communication;

final readonly class NotificationTemplateVersionView
{
    public function __construct(
        public int $templateId,
        public int $version,
        public ?string $subject,
        public string $body,
    ) {}
}
