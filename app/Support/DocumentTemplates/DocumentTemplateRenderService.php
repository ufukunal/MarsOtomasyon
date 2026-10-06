<?php

namespace App\Support\DocumentTemplates;

use App\Models\DocumentTemplate;

final class DocumentTemplateRenderService
{
    public function __construct(
        private readonly TemplateDefinitionValidator $definitions,
        private readonly TemplateExpressionValidator $expressions,
        private readonly SafeTemplateRenderer $renderer,
    ) {}

    /** @return array{sections:list<array{type:string,visible:bool,settings:array<string,mixed>}>} */
    public function render(
        DocumentTemplate $template,
        FrozenDocumentRenderData $data,
    ): array {
        $definition = $this->definitions->normalize($template->definition);
        $this->expressions->validateDefinition($definition);

        return $this->renderArray($definition, $data, $template->render_type);
    }

    /**
     * @param  array<string|int,mixed>  $value
     * @return array<string|int,mixed>
     */
    private function renderArray(array $value, FrozenDocumentRenderData $data, string $renderType): array
    {
        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($item)) {
                $result[$key] = $this->renderer->render($item, $data, $renderType);
            } elseif (is_array($item)) {
                $result[$key] = $this->renderArray($item, $data, $renderType);
            } else {
                $result[$key] = $item;
            }
        }

        return $result;
    }
}
