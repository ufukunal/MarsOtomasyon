<?php

use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;

function marsProtectedGetRoutes(): array
{
    $result = [];

    foreach (Route::getRoutes() as $route) {
        if (! in_array('GET', $route->methods(), true)
            || ! in_array('auth', $route->gatherMiddleware(), true)
            || count($route->parameterNames()) !== 0
            || $route->getName() === null) {
            continue;
        }

        $result[$route->getName()] = [(string) $route->uri()];
    }

    return $result;
}

it('redirects every anonymous visitor away from all authenticated GET screens', function (string $path): void {
    auth()->logout();

    $this->get('/'.ltrim($path, '/'))->assertRedirect(route('login'));
})->with(fn (): array => marsProtectedGetRoutes());

it('indexes a substantial number of protected business screens rather than testing only the dashboard', function (): void {
    expect(count(marsProtectedGetRoutes()))->toBeGreaterThan(30);
});
