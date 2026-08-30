<?php

use App\Modules\Commerce\ProviderRegistry;

it('marks WooCommerce contract verified without claiming merchant verification', function (): void {
    $registry = app(ProviderRegistry::class);

    expect($registry->isContractVerified('woocommerce'))->toBeTrue()
        ->and($registry->isMarketplaceVerified('woocommerce'))->toBeFalse();
});
