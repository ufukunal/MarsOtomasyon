<?php

namespace App\Modules\Reports\Bi;

use App\Modules\Reports\ReportService;

final readonly class AccountAgingDataset implements BiDataset
{
    public function __construct(private ReportService $reports) {}

    public function key(): string
    {
        return 'account_aging';
    }

    public function schemaVersion(): int
    {
        return 1;
    }

    public function fields(): array
    {
        return [
            'account_id' => ['pii' => false],
            'account_code' => ['pii' => false],
            'account_name' => ['pii' => true],
            'account_type' => ['pii' => false],
            'currency' => ['pii' => false],
            'current' => ['pii' => false],
            'days_1_30' => ['pii' => false],
            'days_31_60' => ['pii' => false],
            'days_61_90' => ['pii' => false],
            'days_90_plus' => ['pii' => false],
            'total' => ['pii' => false],
        ];
    }

    public function rows(int $companyId, ?string $watermark = null): iterable
    {
        $snapshot = $this->reports->build($companyId, [
            'as_of' => now()->toDateString(),
            'currency' => null,
            'warehouse_id' => null,
            'account_type' => null,
        ]);

        foreach ($snapshot['aging'] as $row) {
            yield [
                'company_id' => $companyId,
                'account_id' => $row['id'],
                'account_code' => $row['code'],
                'account_name' => $row['name'],
                'account_type' => $row['type'],
                'currency' => $row['currency'],
                'current' => $row['current'],
                'days_1_30' => $row['days_1_30'],
                'days_31_60' => $row['days_31_60'],
                'days_61_90' => $row['days_61_90'],
                'days_90_plus' => $row['days_90_plus'],
                'total' => $row['total'],
            ];
        }
    }

    public function nextWatermark(): ?string
    {
        return null;
    }
}
