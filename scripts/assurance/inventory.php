<?php

declare(strict_types=1);

use App\Foundation\Assurance\AssuranceInventory;
use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var AssuranceInventory $inventory */
$inventory = $app->make(AssuranceInventory::class);
$result = $inventory->build();

$inventoryBlockers = array_values(array_filter(
    $result['critical_gaps'],
    static fn (array $gap): bool => (string) $gap['type'] === 'http-trust-gap',
));

$output = storage_path('app/assurance');
if (! is_dir($output) && ! mkdir($output, 0775, true) && ! is_dir($output)) {
    fwrite(STDERR, "Unable to create assurance artifact directory.\n");
    exit(2);
}

$artifacts = [
    'inventory.json' => $result,
    'coverage-map.json' => $result['coverage_map'],
    'route-authorization-map.json' => $result['route_authorization_map'],
];

foreach ($artifacts as $filename => $payload) {
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
    if (file_put_contents($output.'/'.$filename, $json) === false) {
        fwrite(STDERR, "Unable to write {$filename}.\n");
        exit(2);
    }
}

$summary = $result['summary'];
printf(
    "Assurance inventory: %d routes, %d mutations, %d CLI commands, %d critical surfaces, %d critical gaps.\n",
    $summary['http_routes'],
    $summary['mutating_routes'],
    $summary['cli_commands'],
    $summary['critical_surfaces'],
    $summary['critical_gaps'],
);

if ($result['critical_gaps'] !== []) {
    fwrite(STDOUT, "Critical assurance coverage gaps recorded for later M34 slices:\n");
    foreach ($result['critical_gaps'] as $gap) {
        fwrite(STDOUT, sprintf("- [%s] %s (%s)\n", $gap['type'], $gap['component'], $gap['path/class']));
    }
}

if ($inventoryBlockers !== []) {
    fwrite(STDERR, "Structural assurance inventory blockers detected:\n");
    foreach ($inventoryBlockers as $blocker) {
        fwrite(STDERR, sprintf("- [%s] %s (%s)\n", $blocker['type'], $blocker['component'], $blocker['path/class']));
    }

    exit(1);
}
