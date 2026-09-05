<?php

namespace Tests\Unit;

use App\Modules\Products\Labels\LabelRenderer;
use App\Modules\Products\Labels\LabelTargetResolver;
use App\Modules\Products\Labels\LabelTemplateEngine;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

final class M26LabelRenderingTest extends TestCase
{
    public function test_template_engine_resolves_only_explicit_payload_tokens(): void
    {
        $engine = new LabelTemplateEngine;

        self::assertSame(
            'SKU-001 | Mars | 869000000001',
            $engine->render(
                '{{ product.sku }} | {{product.name}} | {{ barcode }}',
                [
                    'product' => ['sku' => 'SKU-001', 'name' => 'Mars'],
                    'barcode' => '869000000001',
                ],
            ),
        );
    }

    public function test_unknown_template_token_fails_closed(): void
    {
        $engine = new LabelTemplateEngine;

        $this->expectException(ValidationException::class);
        $engine->render('{{ product.secret }}', ['product' => ['name' => 'Mars']]);
    }

    public function test_zpl_output_escapes_control_characters(): void
    {
        $renderer = new LabelRenderer(new LabelTemplateEngine);
        $output = $renderer->render(
            ['value' => "A^B~C\\D\nE"],
            ['body' => '{{ value }}', 'format' => 'zpl'],
            null,
        );

        self::assertStringStartsWith("^XA\n", $output);
        self::assertStringContainsString('A\\5EB\\7EC\\5CD\\0AE', $output);
        self::assertStringEndsWith("^XZ\n", $output);
    }

    public function test_tspl_output_is_wrapped_as_print_command(): void
    {
        $renderer = new LabelRenderer(new LabelTemplateEngine);
        $output = $renderer->render(
            ['value' => 'Warehouse A'],
            [
                'body' => '{{ value }}',
                'format' => 'tspl',
                'width_mm' => '50.00',
                'height_mm' => '30.00',
            ],
            null,
        );

        self::assertStringContainsString('SIZE 50 mm,30 mm', $output);
        self::assertStringContainsString('Warehouse A', $output);
        self::assertStringEndsWith("PRINT 1\n", $output);
    }

    public function test_pdf_output_uses_existing_dompdf_renderer(): void
    {
        $renderer = new LabelRenderer(new LabelTemplateEngine);
        $output = $renderer->render(
            ['value' => 'A4 Label'],
            ['body' => '{{ value }}', 'format' => 'pdf'],
            null,
        );

        self::assertStringStartsWith('%PDF-', $output);
    }

    public function test_package_target_fails_closed_without_canonical_authority(): void
    {
        $resolver = new LabelTargetResolver;

        $this->expectException(ValidationException::class);
        $resolver->resolve(1, 'package', 1);
    }
}
