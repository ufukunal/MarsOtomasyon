<?php

namespace Tests\Feature\Core;

use Tests\TestCase;

final class V163ReferenceShellTest extends TestCase
{
    public function test_application_layout_keeps_v163_reference_shell_contract(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));
        $css = file_get_contents(resource_path('css/v16-3-reference.css'));

        self::assertIsString($layout);
        self::assertIsString($css);
        self::assertStringContainsString('resources/css/v16-3-reference.css', $layout);
        self::assertStringContainsString('Kişiler / Firmalar', $layout);
        self::assertStringContainsString('Ürünler ve Hizmetler', $layout);
        self::assertStringContainsString('Satış Yönetimi', $layout);
        self::assertStringContainsString('Finans İşlemleri', $layout);
        self::assertStringContainsString('Ayarlar / Sistem', $layout);
        self::assertStringContainsString('preacc-simple', $layout);
        self::assertStringContainsString('.app-nav-group', $css);
        self::assertStringContainsString('.v163-doc', $css);
        self::assertStringContainsString('.fin-clean', $css);
    }
}
