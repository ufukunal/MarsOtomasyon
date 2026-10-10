<?php

use Illuminate\Auth\Access\AuthorizationException;

/**
 * Discover every action method whose first executable statement is the
 * centralized mutation permission check. Invoke it with inert parameter
 * values as an anonymous user: no connection, write or network access.
 *
 * This is a real fail-closed authorization behavior check, not a claim
 * that the underlying business action has been end-to-end tested.
 *
 * @return array<string,array{0:class-string,1:string}>
 */
function marsAuthorizationEntrypoints(): array
{
    $manifest = json_decode(
        file_get_contents(__DIR__.'/../../Contracts/source-manifest.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    $cases = [];

    foreach ($manifest as $row) {
        if (! str_starts_with($row['path'], 'app/Actions/')) {
            continue;
        }

        $class = $row['symbol'];
        $reflection = new ReflectionClass($class);
        $lines = file(base_path($row['path']));

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->getDeclaringClass()->getName() !== $class || $method->isConstructor()) {
                continue;
            }

            $text = implode('', array_slice(
                $lines,
                $method->getStartLine() - 1,
                $method->getEndLine() - $method->getStartLine() + 1,
            ));

            if (preg_match('/\{\s*MutationAuthorizer::authorize\(/s', $text) !== 1) {
                continue;
            }

            $cases[$row['domain'].'/'.$reflection->getShortName().'/'.$method->getName()] = [
                $class,
                $method->getName(),
            ];
        }
    }

    return $cases;
}

function marsInertParameter(ReflectionParameter $parameter): mixed
{
    if ($parameter->isDefaultValueAvailable()) {
        return $parameter->getDefaultValue();
    }

    $type = $parameter->getType();
    if ($type === null) {
        return null;
    }

    if ($type instanceof ReflectionUnionType) {
        foreach ($type->getTypes() as $candidate) {
            if ($candidate instanceof ReflectionNamedType && $candidate->getName() !== 'null') {
                $type = $candidate;
                break;
            }
        }
    }

    if (! $type instanceof ReflectionNamedType) {
        return null;
    }

    if ($type->allowsNull()) {
        return null;
    }

    if ($type->isBuiltin()) {
        return match ($type->getName()) {
            'int' => 1,
            'float' => 1.0,
            'bool' => false,
            'string' => 'V4_TEST_ONLY',
            'array' => [],
            'iterable' => [],
            'object' => new stdClass,
            default => null,
        };
    }

    $class = $type->getName();

    if (enum_exists($class)) {
        return $class::cases()[0];
    }

    if (is_a($class, \Carbon\CarbonInterface::class, true)) {
        return \Carbon\CarbonImmutable::parse('2026-10-10');
    }

    if (is_a($class, DateTimeInterface::class, true)) {
        return new DateTimeImmutable('2026-10-10');
    }

    if (interface_exists($class)) {
        return Mockery::mock($class);
    }

    return (new ReflectionClass($class))->newInstanceWithoutConstructor();
}

it('blocks every discovered permission-first action for anonymous users', function (string $class, string $method): void {
    auth()->logout();

    $object = (new ReflectionClass($class))->newInstanceWithoutConstructor();
    $reflection = new ReflectionMethod($class, $method);
    $args = array_map(marsInertParameter(...), $reflection->getParameters());

    expect(fn () => $reflection->invokeArgs($object, $args))
        ->toThrow(AuthorizationException::class);
})->with(fn (): array => marsAuthorizationEntrypoints());

it('maintains a nonempty suite across major write-oriented action modules', function (): void {
    $cases = marsAuthorizationEntrypoints();
    expect(count($cases))->toBeGreaterThan(20);

    foreach (['Sales', 'Purchases', 'Stock', 'Finance', 'Products', 'Channels', 'Production', 'Imports'] as $module) {
        $matched = array_filter(array_keys($cases), fn (string $name): bool => str_starts_with($name, 'Actions/'.$module.'/'));
        expect($matched)->not->toBeEmpty("No guarded mutation was indexed for {$module}");
    }
});
