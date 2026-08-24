<?php

it('serves the liveness endpoint without browser errors', function (): void {
    $page = visit('/up');

    $page
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});

it('redirects the application entry to login without browser errors', function (): void {
    $page = visit('/');

    $page
        ->assertSee('Giriş Yap')
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
