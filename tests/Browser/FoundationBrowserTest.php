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
