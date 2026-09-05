<?php

namespace Tests\Feature;

use App\Foundation\Features\FeatureKey;
use App\Foundation\Features\FeatureRegistry;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class M26LabelFeatureTest extends TestCase
{
    public function test_barcode_label_extension_is_enabled_after_delivery(): void
    {
        self::assertTrue(app(FeatureRegistry::class)->enabled(FeatureKey::BarcodeThermalLabels));
    }

    public function test_label_consumer_routes_are_registered(): void
    {
        foreach ([
            'inventory.labels.templates.store',
            'inventory.labels.printers.store',
            'inventory.labels.render',
            'inventory.labels.reprint',
            'inventory.labels.output',
        ] as $name) {
            self::assertTrue(Route::has($name), "Expected route [{$name}] to be registered.");
        }
    }
}
