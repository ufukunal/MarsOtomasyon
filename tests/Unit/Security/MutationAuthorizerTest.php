<?php

use App\Support\Auth\MutationAuthorizer;
use Illuminate\Auth\Access\AuthorizationException;

it('rejects mutations without an authenticated or delegated actor', function (): void {
    auth()->logout();
    expect(fn () => MutationAuthorizer::authorize('stock.post'))->toThrow(AuthorizationException::class);
    expect(fn () => MutationAuthorizer::runAs(null, fn () => true))->toThrow(AuthorizationException::class);
});
