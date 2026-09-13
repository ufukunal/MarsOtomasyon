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
        self::assertStringContainsString('Teklifler', $layout);
        self::assertStringContainsString('Satış Siparişleri', $layout);
        self::assertStringContainsString('Sevkiyat / İrsaliye', $layout);
        self::assertStringContainsString('Satış Faturaları', $layout);
        self::assertStringContainsString('Finans İşlemleri', $layout);
        self::assertStringContainsString('Ayarlar / Sistem', $layout);
        self::assertStringContainsString('preacc-simple', $layout);
        self::assertStringContainsString('.app-nav-group', $css);
        self::assertStringContainsString('.v163-doc', $css);
        self::assertStringContainsString('.v163-doc-form', $css);
        self::assertStringContainsString('.v163-section', $css);
        self::assertStringContainsString('.v163-table', $css);
        self::assertStringContainsString('.fin-clean', $css);
    }

    public function test_sales_document_forms_use_v163_document_sections(): void
    {
        $quote = file_get_contents(resource_path('views/quotes/form.blade.php'));
        $salesOrder = file_get_contents(resource_path('views/sales-orders/form.blade.php'));
        $dispatch = file_get_contents(resource_path('views/dispatches/create.blade.php'));

        foreach ([$quote, $salesOrder, $dispatch] as $view) {
            self::assertIsString($view);
            self::assertStringContainsString('v163-doc', $view);
            self::assertStringContainsString('v163-section', $view);
            self::assertStringContainsString('v163-footer', $view);
        }

        self::assertStringContainsString('Teklif Kalemleri', $quote);
        self::assertStringContainsString('Sipariş Kalemleri', $salesOrder);
        self::assertStringContainsString('Sevk Kalemleri', $dispatch);
    }
}
