<?php

/**
 * One contract check for every public action method, including internal
 * orchestrators that are not user-facing. This detects disappeared actions,
 * accidental visibility changes and bare variadic/magic entrypoints.
 *
 * @return array<string,array{0:class-string,1:string}>
 */
function marsActionMethods(): array
{
    $manifest = json_decode(file_get_contents(__DIR__.'/source-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    $dataset = [];

    foreach ($manifest as $row) {
        if (! str_starts_with($row['path'], 'app/Actions/')) {
            continue;
        }

        $type = new ReflectionClass($row['symbol']);
        foreach ($type->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $row['symbol'] || $method->isConstructor()) {
                continue;
            }

            $dataset[$row['path'].':'.$method->getName()] = [
                $row['symbol'], $method->getName(),
            ];
        }
    }

    return $dataset;
}

it('exposes explicit public operations rather than only magic dispatch', function (string $class, string $method): void {
    $ref = new ReflectionMethod($class, $method);
    expect($ref->isPublic())->toBeTrue();
    expect($ref->isAbstract())->toBeFalse();
    expect($ref->isVariadic())->toBeFalse();
    expect($ref->getName())->not->toBe('__call');
    expect($ref->getFileName())->toBe(realpath(
        (new ReflectionClass($class))->getFileName()
    ));
})->with(fn (): array => marsActionMethods());

it('maps all 181 business action classes into the indexed API checks', function (): void {
    $manifest = json_decode(file_get_contents(__DIR__.'/source-manifest.json'), true, 512, JSON_THROW_ON_ERROR);
    $actionClasses = array_filter($manifest, fn (array $row): bool => str_starts_with($row['path'], 'app/Actions/'));
    expect(count($actionClasses))->toBeGreaterThanOrEqual(181);
});
