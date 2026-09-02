<?php

namespace App\Modules\Reports\Bi;

use DomainException;

final class BiDatasetRegistry
{
    /** @var array<string, BiDataset> */
    private array $datasets = [];

    public function register(BiDataset $dataset): self
    {
        $key = strtolower(trim($dataset->key()));
        if ($key === '') {
            throw new DomainException('BI dataset key is required.');
        }
        if ($dataset->schemaVersion() < 1) {
            throw new DomainException('BI dataset schema version must be positive.');
        }
        $this->datasets[$key] = $dataset;

        return $this;
    }

    public function get(string $key): BiDataset
    {
        $key = strtolower(trim($key));
        $dataset = $this->datasets[$key] ?? null;
        if (! $dataset instanceof BiDataset) {
            throw new DomainException('BI dataset is not registered.');
        }

        return $dataset;
    }
}
