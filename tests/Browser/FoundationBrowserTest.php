<?php

it('serves the liveness endpoint without browser errors', function (): void {
    $page = visit('/up');

    $page
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

it('renders the foundation shell without browser errors', function (): void {
    $page = visit('/');

    $page
        ->assertSee('Altyapı kuruluyor')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

it('renders the internal login form without browser errors', function (): void {
    $page = visit('/login');

    $page
        ->assertSee('Giriş Yap')
        ->assertSee('E-posta')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});
