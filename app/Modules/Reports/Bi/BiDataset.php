<?php

namespace App\Modules\Reports\Bi;

interface BiDataset
{
    public function key(): string;

    public function schemaVersion(): int;

    /** @return array<string, array{pii:bool}> */
    public function fields(): array;

    /**
     * Every row must contain internal company_id for scope verification.
     * @return iterable<array<string, mixed>>
     */
    public function rows(int $companyId, ?string $watermark = null): iterable;

    public function nextWatermark(): ?string;
}
