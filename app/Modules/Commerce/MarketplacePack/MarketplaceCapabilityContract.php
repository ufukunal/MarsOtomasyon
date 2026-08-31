<?php

namespace App\Modules\Commerce\MarketplacePack;

use App\Modules\Commerce\ProviderRegistry;
use DomainException;

final readonly class MarketplaceCapabilityContract
{
    /** @var list<string> */
    private const PROVIDERS = ['trendyol', 'hepsiburada', 'amazon', 'n11', 'pttavm', 'idefix', 'allesgo'];

    /**
     * @var array<string,array{
     *     media:array{mode:string,capability:?string},
     *     operations:array<string,array{mode:string,capability:?string}>,
     *     smoke:array{mode:string,capability:?string}
     * }>
     */
    private const PROFILES = [
        'trendyol' => [
            'media' => ['mode' => 'manual', 'capability' => 'media_manual'],
            'operations' => [
                'cancel' => ['mode' => 'api_contract', 'capability' => 'order_cancel_contract'],
                'return' => ['mode' => 'api_contract', 'capability' => 'return_create_contract'],
                'questions' => ['mode' => 'api_contract', 'capability' => 'questions_contract'],
                'invoice' => ['mode' => 'api_contract', 'capability' => 'invoice_contract'],
                'settlement' => ['mode' => 'api_contract', 'capability' => 'settlement_contract'],
            ],
            'smoke' => ['mode' => 'stage_contract', 'capability' => null],
        ],
        'hepsiburada' => [
            'media' => ['mode' => 'manual', 'capability' => 'media_manual'],
            'operations' => [
                'cancel' => ['mode' => 'unsupported', 'capability' => null],
                'return' => ['mode' => 'api_contract', 'capability' => 'claim_contract'],
                'questions' => ['mode' => 'unsupported', 'capability' => null],
                'invoice' => ['mode' => 'api_contract', 'capability' => 'invoice_contract'],
                'settlement' => ['mode' => 'evidence', 'capability' => 'settlement_evidence'],
            ],
            'smoke' => ['mode' => 'sit_contract', 'capability' => null],
        ],
        'amazon' => [
            'media' => ['mode' => 'schema_driven', 'capability' => 'media_schema_driven'],
            'operations' => [
                'cancel' => ['mode' => 'unsupported', 'capability' => null],
                'return' => ['mode' => 'evidence', 'capability' => 'returns_report_contract'],
                'questions' => ['mode' => 'unsupported', 'capability' => null],
                'invoice' => ['mode' => 'unsupported', 'capability' => null],
                'settlement' => ['mode' => 'evidence', 'capability' => 'settlement_report_contract'],
            ],
            'smoke' => ['mode' => 'sandbox_contract', 'capability' => 'sandbox_contract'],
        ],
        'n11' => [
            'media' => ['mode' => 'product_contract', 'capability' => 'media_product_contract'],
            'operations' => [
                'cancel' => ['mode' => 'unsupported', 'capability' => null],
                'return' => ['mode' => 'evidence', 'capability' => 'return_evidence'],
                'questions' => ['mode' => 'unsupported', 'capability' => null],
                'invoice' => ['mode' => 'api_contract', 'capability' => 'invoice_contract'],
                'settlement' => ['mode' => 'evidence', 'capability' => 'settlement_evidence'],
            ],
            'smoke' => ['mode' => 'fixture_contract', 'capability' => null],
        ],
        'pttavm' => [
            'media' => ['mode' => 'product_contract', 'capability' => 'media_product_contract'],
            'operations' => [
                'cancel' => ['mode' => 'unsupported', 'capability' => null],
                'return' => ['mode' => 'evidence', 'capability' => 'return_evidence'],
                'questions' => ['mode' => 'unsupported', 'capability' => null],
                'invoice' => ['mode' => 'api_contract', 'capability' => 'invoice_contract'],
                'settlement' => ['mode' => 'evidence', 'capability' => 'settlement_evidence'],
            ],
            'smoke' => ['mode' => 'fixture_contract', 'capability' => null],
        ],
        'idefix' => [
            'media' => ['mode' => 'product_contract', 'capability' => 'media_product_contract'],
            'operations' => [
                'cancel' => ['mode' => 'api_contract', 'capability' => 'cancel_contract'],
                'return' => ['mode' => 'api_contract', 'capability' => 'return_contract'],
                'questions' => ['mode' => 'api_contract', 'capability' => 'questions_contract'],
                'invoice' => ['mode' => 'api_contract', 'capability' => 'invoice_contract'],
                'settlement' => ['mode' => 'evidence', 'capability' => 'settlement_evidence'],
            ],
            'smoke' => ['mode' => 'fixture_contract', 'capability' => null],
        ],
        'allesgo' => [
            'media' => ['mode' => 'product_contract', 'capability' => 'media_product_contract'],
            'operations' => [
                'cancel' => ['mode' => 'unsupported', 'capability' => null],
                'return' => ['mode' => 'evidence', 'capability' => 'return_evidence'],
                'questions' => ['mode' => 'api_contract', 'capability' => 'questions_contract'],
                'invoice' => ['mode' => 'api_contract', 'capability' => 'invoice_contract'],
                'settlement' => ['mode' => 'evidence', 'capability' => 'settlement_evidence'],
            ],
            'smoke' => ['mode' => 'sandbox_contract', 'capability' => 'sandbox_contract'],
        ],
    ];

    public function __construct(private ProviderRegistry $registry) {}

    /**
     * @return array{
     *     media:array{mode:string,capability:?string},
     *     operations:array<string,array{mode:string,capability:?string}>,
     *     smoke:array{mode:string,capability:?string}
     * }
     */
    public function profile(string $provider): array
    {
        $provider = strtolower(trim($provider));
        if (! in_array($provider, self::PROVIDERS, true)) {
            throw new DomainException('Marketplace capability provider is unsupported.');
        }
        if (! $this->registry->isContractVerified($provider)) {
            throw new DomainException('Marketplace capability provider is not contract verified.');
        }

        $profile = self::PROFILES[$provider];
        $this->assertCapability($provider, $profile['media']['capability']);
        $this->assertCapability($provider, $profile['smoke']['capability']);
        foreach ($profile['operations'] as $operation) {
            $this->assertCapability($provider, $operation['capability']);
        }

        return $profile;
    }

    /** @return array{mode:string,capability:?string} */
    public function media(string $provider): array
    {
        return $this->profile($provider)['media'];
    }

    /** @return array{mode:string,capability:?string} */
    public function operation(string $provider, string $operation): array
    {
        $operation = strtolower(trim($operation));
        $operations = $this->profile($provider)['operations'];
        if (! array_key_exists($operation, $operations)) {
            throw new DomainException('Marketplace operation capability is unsupported.');
        }

        return $operations[$operation];
    }

    /** @return array{mode:string,capability:?string} */
    public function smoke(string $provider): array
    {
        return $this->profile($provider)['smoke'];
    }

    private function assertCapability(string $provider, ?string $capability): void
    {
        if ($capability !== null && ! $this->registry->supports($provider, $capability)) {
            throw new DomainException('Marketplace capability profile drift detected for '.$provider.': '.$capability.'.');
        }
    }
}
