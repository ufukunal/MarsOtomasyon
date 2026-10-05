<?php

namespace App\Support\DocumentTemplates;

use App\Models\DocumentTemplate;
use Illuminate\Contracts\View\Factory as ViewFactory;

final class DocumentTemplatePreviewRenderer
{
    public function __construct(
        private readonly TemplateDefinitionValidator $validator,
        private readonly ViewFactory $view,
    ) {}

    public function renderDefinition(array $definition, string $name = 'Template Önizleme'): string
    {
        $normalized = $this->validator->normalize($definition);

        return $this->view->make('document-templates.preview', [
            'name' => $name,
            'sections' => $normalized['sections'],
        ])->render();
    }

    public function render(DocumentTemplate $template): string
    {
        return $this->renderDefinition($template->definition, "{$template->name} · Rev.{$template->revision_no}");
    }
}
