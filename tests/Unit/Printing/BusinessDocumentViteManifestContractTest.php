<?php

it('includes every business-layout asset in the production Vite entrypoints', function (): void {
    $layout = file_get_contents(base_path('resources/views/layouts/app.blade.php'));
    $vite = file_get_contents(base_path('vite.config.js'));
    $deploy = file_get_contents(base_path('ops/uat-fresh-web-update.sh'));

    preg_match('/@vite\(\[([^\]]+)\]\)/', $layout, $matches);
    expect($matches)->toHaveCount(2);

    preg_match_all("/'([^']+)'/", $matches[1], $assets);
    foreach ($assets[1] as $asset) {
        expect($vite)->toContain("'".$asset."'");
    }

    expect($deploy)->toContain('document preview stylesheet absent from Vite manifest');
});
