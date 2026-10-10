<?php

function marsManifest(): array
{
    return json_decode(file_get_contents(__DIR__.'/source-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
}

it('can autoload every indexed production symbol and map it to the correct file', function (string $path, string $symbol, string $domain): void {
    $loaded = class_exists($symbol) || interface_exists($symbol) || trait_exists($symbol);
    expect($loaded)->toBeTrue("Missing production symbol: {$symbol} in {$domain}");

    $reflection = new ReflectionClass($symbol);
    expect(realpath($reflection->getFileName()))->toBe(realpath(base_path($path)));
})->with(fn (): array => array_map(
    fn (array $row): array => [$row['path'], $row['symbol'], $row['domain']],
    marsManifest(),
));

it('requires every action to expose an explicit public business operation', function (string $path, string $symbol): void {
    $reflection = new ReflectionClass($symbol);
    $methods = array_filter(
        $reflection->getMethods(ReflectionMethod::IS_PUBLIC),
        fn (ReflectionMethod $method): bool =>
            $method->getDeclaringClass()->getName() === $symbol
            && ! $method->isConstructor()
            && ($method->getName() === '__invoke' || ! str_starts_with($method->getName(), '__')),
    );
    expect($methods)->not->toBeEmpty("No public operation in {$path}");
})->with(fn (): array => array_values(array_map(
    fn (array $row): array => [$row['path'], $row['symbol']],
    array_filter(marsManifest(), fn (array $row): bool => str_starts_with($row['path'], 'app/Actions/')),
)));

it('detects every unindexed production PHP file and every stale inventory record', function (): void {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(base_path('app'), FilesystemIterator::SKIP_DOTS));
    $actual = [];
    foreach ($it as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $actual[] = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen(base_path()) + 1));
        }
    }
    sort($actual);
    $declared = array_column(marsManifest(), 'path');
    sort($declared);
    expect($declared)->toBe($actual);
});

it('assigns each production symbol to exactly one domain with no duplicate paths', function (): void {
    $manifest = marsManifest();
    $paths = array_column($manifest, 'path');
    $symbols = array_column($manifest, 'symbol');
    expect($paths)->toHaveCount(count(array_unique($paths)))
        ->and($symbols)->toHaveCount(count(array_unique($symbols)));
    foreach ($manifest as $row) {
        expect($row['domain'])->not->toBeEmpty();
    }
});
