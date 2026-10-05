<?php

namespace App\Support\Reporting;

use App\Contracts\Reporting\ReportQuery;
use Illuminate\Contracts\Container\Container;
use LogicException;

final class ReportRegistry
{
    /** @var array<string,ReportQuery>|null */
    private ?array $queries = null;

    public function __construct(private readonly Container $container) {}

    public function query(string $key): ReportQuery
    {
        $queries = $this->queries();

        if (! isset($queries[$key])) {
            throw new LogicException("Rapor registry key bulunamadı: {$key}.");
        }

        return $queries[$key];
    }

    /** @return list<ReportDefinition> */
    public function definitions(): array
    {
        return array_values(array_map(
            fn (ReportQuery $query): ReportDefinition => $query->definition(),
            $this->queries(),
        ));
    }

    /** @return array<string,ReportQuery> */
    private function queries(): array
    {
        if ($this->queries !== null) {
            return $this->queries;
        }

        $resolved = [];

        foreach ((array) config('reporting.queries', []) as $class) {
            if (! is_string($class) || ! is_a($class, ReportQuery::class, true)) {
                throw new LogicException('Reporting query config ReportQuery sınıfı olmalıdır.');
            }

            $query = $this->container->make($class);
            $key = $query->definition()->key;

            if (isset($resolved[$key])) {
                throw new LogicException("Rapor registry key tekrarlı: {$key}.");
            }

            $resolved[$key] = $query;
        }

        ksort($resolved);

        return $this->queries = $resolved;
    }
}
