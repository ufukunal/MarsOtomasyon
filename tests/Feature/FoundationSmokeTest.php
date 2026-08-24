<?php

namespace Tests\Feature;

use Tests\TestCase;

final class FoundationSmokeTest extends TestCase
{
    public function test_home_route_is_bootable(): void
    {
        $this->get('/')->assertOk()->assertSee('MarsOtomasyon');
    }

    public function test_framework_health_route_is_bootable(): void
    {
        $this->get('/up')->assertOk();
    }
}
