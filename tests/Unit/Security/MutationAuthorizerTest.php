<?php

use App\Support\Auth\MutationAuthorizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;

it('rejects mutations without an authenticated or delegated actor', function (): void {
    Auth::forgetGuards();
    expect(fn () => MutationAuthorizer::authorize('stock.post'))->toThrow(AuthorizationException::class);
    expect(fn () => MutationAuthorizer::runAs(null, fn () => true))->toThrow(AuthorizationException::class);
});
