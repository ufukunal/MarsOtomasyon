<?php

use Tests\TestCase;

uses(TestCase::class)->in('Unit', 'Contracts', 'Feature', 'Integration', 'Performance');

// Offline HTTP authorization tests exercise middleware; Vite bundles are covered by CI build.
beforeEach(function (): void {
    $this->withoutVite();
})->in('Feature');
