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
        $dataset = $this->datasets[strtolower(trim($key))] ?? null;
        if (! $dataset instanceof BiDataset) {
            throw new DomainException('BI dataset is not registered.');
        }

        return $dataset;
    }

    /** @return list<array{key:string,schema_version:int,fields:array<string,array{pii:bool}>}> */
    public function catalog(): array
    {
        $catalog = [];
        foreach ($this->datasets as $dataset) {
            $catalog[] = [
                'key' => $dataset->key(),
                'schema_version' => $dataset->schemaVersion(),
                'fields' => $dataset->fields(),
            ];
        }

        return $catalog;
    }
}
