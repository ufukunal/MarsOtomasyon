<?php

use Illuminate\Support\Str;
use Livewire\Component;

function marsLivewireViewBindings(): array
{
    $directory = dirname(__DIR__, 2).'/resources/views/livewire';
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
    );
    $matched = [];

    foreach ($iterator as $file) {
        if (! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $relative = substr($file->getPathname(), strlen($directory) + 1);
        $name = substr($relative, 0, -strlen('.blade.php'));
        $parts = array_map(Str::studly(...), explode(DIRECTORY_SEPARATOR, $name));
        $class = 'App\\Livewire\\'.implode('\\', $parts);

        if (! class_exists($class) || ! is_subclass_of($class, Component::class)) {
            continue;
        }

        $matched[$class] = [$class, $file->getPathname()];
    }

    return $matched;
}

/**
 * Only templates declaring actions should enter action binding cases.
 * Templates with no such actions are still indexed by the separate screen check.
 *
 * @return array<string,array{0:class-string,1:string}>
 */
function marsLivewireActionBindingViews(): array
{
    return array_filter(
        marsLivewireViewBindings(),
        static fn (array $case): bool => preg_match(
            '/wire:(?:click|submit|change)(?:\\.[\\w-]+)*="\\s*[A-Za-z_]\\w*/',
            (string) file_get_contents($case[1]),
        ) === 1,
    );
}

/** @return array<string,array{0:class-string,1:string}> */
function marsLivewireStateBindingViews(): array
{
    return array_filter(
        marsLivewireViewBindings(),
        static fn (array $case): bool => preg_match(
            '/wire:model(?:\\.[\\w-]+)*="\\s*[A-Za-z_]\\w*/',
            (string) file_get_contents($case[1]),
        ) === 1,
    );
}

it('keeps each Livewire click and form submit binding connected to a callable public method', function (string $class, string $bladePath): void {
    $blade = file_get_contents($bladePath);
    $reflection = new ReflectionClass($class);

    preg_match_all(
        '/wire:(?:click|submit|change)(?:\.[\w-]+)*="\s*([A-Za-z_]\w*)/',
        $blade,
        $matches,
    );

    expect($matches[1])->not->toBeEmpty();

    foreach (array_unique($matches[1]) as $method) {
        expect($reflection->hasMethod($method))
            ->toBeTrue("Missing Livewire action {$class}::{$method} from {$bladePath}");
        expect($reflection->getMethod($method)->isPublic())->toBeTrue();
    }
})->with(fn (): array => marsLivewireActionBindingViews());

it('keeps Livewire form bindings attached to public component state', function (string $class, string $bladePath): void {
    $blade = file_get_contents($bladePath);
    $reflection = new ReflectionClass($class);

    preg_match_all(
        '/wire:model(?:\.[\w-]+)*="\s*([A-Za-z_]\w*)/',
        $blade,
        $matches,
    );

    expect($matches[1])->not->toBeEmpty();

    foreach (array_unique($matches[1]) as $property) {
        expect($reflection->hasProperty($property))
            ->toBeTrue("Missing form state {$class}::\${$property} from {$bladePath}");
        expect($reflection->getProperty($property)->isPublic())->toBeTrue();
    }
})->with(fn (): array => marsLivewireStateBindingViews());

it('indexes real application screen templates rather than testing a hard-coded example', function (): void {
    $cases = marsLivewireViewBindings();

    expect(count($cases))->toBeGreaterThanOrEqual(35);
    foreach (['ProductForm', 'ContactForm', 'ReportCenter', 'ImportCenter', 'ProductionOrderCenter'] as $screen) {
        expect(array_reduce(array_keys($cases), fn (bool $found, string $class): bool => $found || str_ends_with($class, '\\'.$screen), false))->toBeTrue();
    }
});
