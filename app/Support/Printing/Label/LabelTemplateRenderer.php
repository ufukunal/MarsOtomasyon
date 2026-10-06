<?php

namespace App\Support\Printing\Label;

use App\Models\DocumentTemplate;
use App\Support\DocumentTemplates\DocumentTemplateRenderService;
use App\Support\DocumentTemplates\FrozenDocumentRenderData;
use DomainException;
use Illuminate\Contracts\View\Factory as ViewFactory;

final class LabelTemplateRenderer
{
    public function __construct(
        private readonly DocumentTemplateRenderService $templates,
        private readonly ViewFactory $view,
    ) {}

    /** @return array{render_type:string,content:string} */
    public function render(DocumentTemplate $template, FrozenDocumentRenderData $data): array
    {
        $definition = $this->templates->render($template, $data);
        $sections = array_values(array_filter(
            $definition['sections'],
            fn (array $section): bool => $section['visible'],
        ));

        return match ($template->render_type) {
            'html_pdf' => [
                'render_type' => 'html_pdf',
                'content' => $this->view->make('printing.label', [
                    'template' => $template,
                    'sections' => $sections,
                ])->render(),
            ],
            'zpl' => [
                'render_type' => 'zpl',
                'content' => $this->zpl($sections),
            ],
            'text' => [
                'render_type' => 'text',
                'content' => $this->text($sections),
            ],
            default => throw new DomainException('Etiket template render tipi desteklenmiyor.'),
        };
    }

    /** @param list<array<string,mixed>> $sections */
    private function zpl(array $sections): string
    {
        $y = 30;
        $commands = ['^XA', '^CI28'];

        foreach ($sections as $section) {
            $text = trim((string) ($section['settings']['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $safe = str_replace(['^', '~', "\n", "\r"], ['', '', ' ', ' '], $text);
            $commands[] = "^FO30,{$y}^A0N,28,28^FD{$safe}^FS";
            $y += 38;
        }

        $commands[] = '^XZ';

        return implode("\n", $commands);
    }

    /** @param list<array<string,mixed>> $sections */
    private function text(array $sections): string
    {
        return collect($sections)
            ->map(fn (array $section): string => trim((string) ($section['settings']['text'] ?? '')))
            ->filter()
            ->implode(PHP_EOL);
    }
}
