<?php

namespace App\Livewire\Reporting;

use App\Actions\DocumentTemplates\CreateDocumentTemplateRevision;
use App\Actions\DocumentTemplates\DeactivateDocumentTemplate;
use App\Actions\DocumentTemplates\SetDefaultDocumentTemplate;
use App\Models\DocumentTemplate;
use App\Support\DocumentTemplates\DocumentTemplatePreviewRenderer;
use App\Support\DocumentTemplates\TemplateDefinitionValidator;
use App\Support\Period\PeriodContext;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class DocumentTemplateDesigner extends Component
{
    public string $templateKey = '';

    public string $name = '';

    public string $renderType = 'html_pdf';

    public string $paperCode = 'A4';

    public string $widthMm = '';

    public string $heightMm = '';

    public bool $makeDefault = true;

    /** @var list<array{type:string,visible:bool,settings:array<string,mixed>}> */
    public array $sections = [];

    public string $newSectionType = 'header';

    public ?int $sourceRevisionId = null;

    public ?string $message = null;

    public ?string $previewHtml = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('document_templates.view'), 403);
        PeriodContext::ensure();
    }

    public function addSection(): void
    {
        abort_unless(in_array($this->newSectionType, TemplateDefinitionValidator::SECTION_TYPES, true), 422);

        $this->sections[] = [
            'type' => $this->newSectionType,
            'visible' => true,
            'settings' => [],
        ];
        $this->previewHtml = null;
    }

    public function removeSection(int $index): void
    {
        abort_unless(isset($this->sections[$index]), 422);
        array_splice($this->sections, $index, 1);
        $this->previewHtml = null;
    }

    public function moveSection(int $index, int $direction): void
    {
        abort_unless(isset($this->sections[$index]) && in_array($direction, [-1, 1], true), 422);
        $target = $index + $direction;

        if ($target < 0 || $target >= count($this->sections)) {
            return;
        }

        [$this->sections[$index], $this->sections[$target]] = [$this->sections[$target], $this->sections[$index]];
        $this->previewHtml = null;
    }

    public function loadRevision(int $templateId): void
    {
        $template = DocumentTemplate::query()
            ->whereKey($templateId)
            ->where('company_id', PeriodContext::companyId())
            ->firstOrFail();

        $this->sourceRevisionId = $template->id;
        $this->templateKey = $template->template_key;
        $this->name = $template->name;
        $this->renderType = $template->render_type;
        $this->paperCode = (string) ($template->paper_code ?? '');
        $this->widthMm = (string) ($template->width_mm ?? '');
        $this->heightMm = (string) ($template->height_mm ?? '');
        $this->makeDefault = (bool) $template->is_default;
        $this->sections = array_values($template->definition['sections'] ?? []);
        $this->message = "Rev.{$template->revision_no} düzenleme kaynağı olarak yüklendi. Kaydetmek yeni revizyon oluşturur.";
        $this->previewHtml = null;
    }

    public function saveRevision(): void
    {
        $template = app(CreateDocumentTemplateRevision::class)->handle(
            templateKey: $this->templateKey,
            name: $this->name,
            renderType: $this->renderType,
            definition: ['sections' => $this->sections],
            paperCode: $this->paperCode,
            widthMm: $this->widthMm,
            heightMm: $this->heightMm,
            makeDefault: $this->makeDefault,
        );

        $this->sourceRevisionId = $template->id;
        $this->message = "{$template->template_key} Rev.{$template->revision_no} oluşturuldu.";
        $this->previewHtml = app(DocumentTemplatePreviewRenderer::class)->render($template);
    }

    public function preview(): void
    {
        $this->previewHtml = app(DocumentTemplatePreviewRenderer::class)->renderDefinition(
            ['sections' => $this->sections],
            $this->name !== '' ? $this->name : 'Template Önizleme',
        );
    }

    public function setDefault(int $templateId): void
    {
        $template = app(SetDefaultDocumentTemplate::class)->handle($templateId);
        $this->message = "{$template->template_key} Rev.{$template->revision_no} varsayılan yapıldı.";
    }

    public function deactivate(int $templateId): void
    {
        $template = app(DeactivateDocumentTemplate::class)->handle($templateId);
        $this->message = "{$template->template_key} Rev.{$template->revision_no} pasifleştirildi.";
    }

    public function render(): View
    {
        $templates = DocumentTemplate::query()
            ->where('company_id', PeriodContext::companyId())
            ->orderBy('template_key')
            ->orderByDesc('revision_no')
            ->get();

        return view('livewire.reporting.document-template-designer', [
            'templates' => $templates,
            'sectionTypes' => TemplateDefinitionValidator::SECTION_TYPES,
        ])->layout('layouts.app', [
            'pageTitle' => 'Belge Şablonları',
            'pageDescription' => 'Bölüm tabanlı immutable Rev.N belge şablonları.',
        ]);
    }
}
