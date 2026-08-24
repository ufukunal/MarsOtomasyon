<?php

it('renders the foundation shell without browser errors', function (): void {
    $page = visit('/');

    $page
        ->assertSee('Altyapı kuruluyor')
        ->assertNoJavaScriptErrors()
        ->assertNoConsoleLogs();
});
