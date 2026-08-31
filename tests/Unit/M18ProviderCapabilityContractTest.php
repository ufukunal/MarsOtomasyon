<?php

use App\Modules\Commerce\MarketplacePack\MarketplaceCapabilityContract;
use App\Modules\Commerce\ProviderRegistry;
use DomainException;
use Tests\TestCase;

uses(TestCase::class);

it('locks deterministic M18 media operation and smoke capability profiles', function (): void {
    $contracts = app(MarketplaceCapabilityContract::class);
    $registry = app(ProviderRegistry::class);
    $expected = [
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

    foreach ($expected as $provider => $profile) {
        expect($contracts->profile($provider))->toBe($profile)
            ->and($contracts->media($provider))->toBe($profile['media'])
            ->and($contracts->smoke($provider))->toBe($profile['smoke'])
            ->and($registry->isContractVerified($provider))->toBeTrue()
            ->and($registry->isMarketplaceVerified($provider))->toBeFalse();

        foreach ($profile['operations'] as $operation => $definition) {
            expect($contracts->operation($provider, $operation))->toBe($definition);
        }
    }
});

it('fails closed for unknown M18 provider and operation capability', function (): void {
    $contracts = app(MarketplaceCapabilityContract::class);

    expect(fn () => $contracts->profile('unknown-marketplace'))
        ->toThrow(DomainException::class, 'Marketplace capability provider is unsupported.')
        ->and(fn () => $contracts->operation('n11', 'unknown-operation'))
        ->toThrow(DomainException::class, 'Marketplace operation capability is unsupported.');
});
