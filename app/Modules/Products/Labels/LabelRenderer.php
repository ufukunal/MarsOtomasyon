<?php

namespace App\Modules\Products\Labels;

use Dompdf\Dompdf;
use Illuminate\Validation\ValidationException;

final readonly class LabelRenderer
{
    public function __construct(private LabelTemplateEngine $engine) {}

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $template
     * @param array<string, mixed>|null $printer
     */
    public function render(array $payload, array $template, ?array $printer): string
    {
        $plain = $this->engine->render((string) $template['body'], $payload);
        $format = (string) $template['format'];

        return match ($format) {
            'pdf' => $this->pdf($plain, $template, $printer),
            'zpl' => $this->zpl($plain),
            'tspl' => $this->tspl($plain, $template, $printer),
            default => throw ValidationException::withMessages(['format' => 'Unsupported label output format.']),
        };
    }

    /** @param array<string, mixed> $template @param array<string, mixed>|null $printer */
    private function pdf(string $plain, array $template, ?array $printer): string
    {
        $dompdf = new Dompdf;
        $escaped = htmlspecialchars($plain, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $dompdf->loadHtml('<!doctype html><html><meta charset="utf-8"><body><pre style="font-family:DejaVu Sans,sans-serif;white-space:pre-wrap">'.$escaped.'</pre></body></html>');

        $width = $printer['width_mm'] ?? $template['width_mm'] ?? null;
        $height = $printer['height_mm'] ?? $template['height_mm'] ?? null;
        if (is_numeric($width) && is_numeric($height) && (float) $width > 0 && (float) $height > 0) {
            $dompdf->setPaper([0, 0, (float) $width * 72 / 25.4, (float) $height * 72 / 25.4]);
        } else {
            $dompdf->setPaper('a4');
        }

        $dompdf->render();

        return $dompdf->output();
    }

    private function zpl(string $plain): string
    {
        $escaped = str_replace(
            ['\\', '^', '~', "\r", "\n"],
            ['\\5C', '\\5E', '\\7E', '', '\\0A'],
            $plain,
        );

        return "^XA\n^FO20,20^A0N,28,28^FH\\^FD{$escaped}^FS\n^XZ\n";
    }

    /** @param array<string, mixed> $template @param array<string, mixed>|null $printer */
    private function tspl(string $plain, array $template, ?array $printer): string
    {
        $width = (float) ($printer['width_mm'] ?? $template['width_mm'] ?? 50);
        $height = (float) ($printer['height_mm'] ?? $template['height_mm'] ?? 30);
        $escaped = str_replace(['\\', '"', "\r", "\n"], ['\\\\', '\\"', '', '\\n'], $plain);

        return "SIZE {$width} mm,{$height} mm\nCLS\nTEXT 20,20,\"0\",0,1,1,\"{$escaped}\"\nPRINT 1\n";
    }
}
