<?php

it('builds and validates the immutable Fresh UAT image only on CI before pushing', function (): void {
    $source = file_get_contents(base_path('ops/uat-fresh-ci-build-push.sh'));
    expect($source)
        ->toContain('Dockerfile.uat')
        ->toContain('org.opencontainers.image.revision')
        ->toContain('docker run --rm --user 33:33')
        ->toContain('php /var/www/html/artisan view:cache')
        ->toContain('docker push "$IMAGE"')
        ->not->toContain('docker compose up');
    expect(strpos($source, 'CI IMAGE PREFLIGHT'))
        ->toBeLessThan(strpos($source, 'docker push "$IMAGE"'));
});

it('promotes only the Fresh web service after verifying period schema and effective role grants', function (): void {
    $source = file_get_contents(base_path('ops/uat-fresh-ci-image-promote.sh'));
    expect($source)
        ->toContain("PG=mars-uat-fresh-postgres-1")
        ->toContain("VK=mars-uat-fresh-valkey-1")
        ->toContain("WEB=mars-uat-fresh-web-1")
        ->toContain('role_has_permissions')
        ->toContain('org.opencontainers.image.revision')
        ->toContain('docker pull "$NEW_IMAGE"')
        ->toContain('up -d --no-deps --pull never --force-recreate web')
        ->toContain('FRESH_WEB_ROLLBACK_ATTEMPTED=YES')
        ->toContain('FRESH_POSTGRES_UNCHANGED=YES')
        ->toContain('FRESH_VALKEY_UNCHANGED=YES')
        ->toContain('FRESH_23=PASS')
        ->not->toContain('docker build')
        ->not->toContain('artisan migrate')
        ->not->toContain('docker compose down');
    expect(strpos($source, 'Fresh schema and effective role readiness'))
        ->toBeLessThan(strpos($source, 'docker pull "$NEW_IMAGE"'));
});

it('exports both CI and Fresh promotion scripts in a hash-verifiable release package', function (): void {
    $source = file_get_contents(base_path('ops/uat-fresh-export-release.sh'));
    expect($source)
        ->toContain('ops/uat-fresh-ci-build-push.sh')
        ->toContain('ops/uat-fresh-ci-image-promote.sh')
        ->toContain('mars-fresh-18-23-$SHA.tar.gz')
        ->toContain('sha256sum');
});

it('transports an immutable CI-built image offline without invoking a PROD build', function (): void {
    $export = file_get_contents(base_path('ops/uat-fresh-ci-export-image.sh'));
    $import = file_get_contents(base_path('ops/uat-fresh-import-image.sh'));
    $promote = file_get_contents(base_path('ops/uat-fresh-ci-image-promote.sh'));
    $package = file_get_contents(base_path('ops/uat-fresh-export-release.sh'));

    expect($export)->toContain('sudo docker save "$IMAGE"')
        ->toContain('sha256sum')
        ->toContain('CI_OFFLINE_IMAGE=PASS');
    expect($import)->toContain('sha256sum -c --status')
        ->toContain('sudo docker load -i "$FILE"')
        ->toContain('MARS_FRESH_OFFLINE_IMAGE=1 bash "$PROMOTE" "$SHA"')
        ->not->toContain('docker build');
    expect($promote)->toContain('MARS_FRESH_OFFLINE_IMAGE')
        ->toContain('sudo docker image inspect "$NEW_IMAGE"');
    expect($package)->toContain('ops/uat-fresh-ci-export-image.sh')
        ->toContain('ops/uat-fresh-import-image.sh');
});
