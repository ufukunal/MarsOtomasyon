<?php

namespace Tests\Feature;

use Tests\TestCase;

final class FoundationSmokeTest extends TestCase
{
    public function test_application_entry_redirects_guests_to_login(): void
    {
        $this->withoutVite();

        $this->get('/')
            ->assertRedirect('/login');
    }

    public function test_framework_health_route_is_bootable(): void
    {
        $this->get('/up')->assertOk();
    }
}
