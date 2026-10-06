<?php

namespace App\Support\Printing\Label;

use App\Enums\PrintType;
use App\Models\DocumentTemplate;
use App\Support\Period\PeriodContext;
use App\Support\Printing\PrintManager;
use App\Support\Printing\PrintResult;
use DomainException;

final class LabelPrintService
{
    public const PRODUCT_TEMPLATE_KEY = 'label.product';

    public const CARTON_TEMPLATE_KEY = 'label.carton';

    public function product(ProductLabelData $data, int $quantity = 1): PrintResult
    {
        $template = $this->defaultTemplate(self::PRODUCT_TEMPLATE_KEY);
        $rendered = $this->renderer->render($template, $data->toRenderData());

        return PrintManager::sendTemplate(
            PrintType::ProductLabel,
            $template,
            $rendered['content'],
            'product-'.$data->productId.'.'.$this->extension($template),
            [
                'source_type' => 'product',
                'source_id' => $data->productId,
                'quantity' => max(1, $quantity),
                'revision_selection' => 'current',
            ],
        );
    }

    public function carton(CartonLabelData $data, int $quantity = 1): PrintResult
    {
        $template = $this->defaultTemplate(self::CARTON_TEMPLATE_KEY);
        $rendered = $this->renderer->render($template, $data->toRenderData());

        return PrintManager::sendTemplate(
            PrintType::CartonLabel,
            $template,
            $rendered['content'],
            'carton-'.$data->sourceType.'-'.$data->sourceId.'.'.$this->extension($template),
            [
                'source_type' => $data->sourceType,
                'source_id' => $data->sourceId,
                'quantity' => max(1, $quantity),
                'revision_selection' => 'current',
            ],
        );
    }

    private function defaultTemplate(string $templateKey): DocumentTemplate
    {
        PeriodContext::ensure();

        $template = DocumentTemplate::query()
            ->where('company_id', PeriodContext::companyId())
            ->where('template_key', $templateKey)
            ->where('is_active', true)
            ->where('is_default', true)
            ->first();

        if (! $template) {
            throw new DomainException("Aktif varsayılan etiket template bulunamadı: {$templateKey}.");
        }

        return $template;
    }

    private function extension(DocumentTemplate $template): string
    {
        return match ($template->render_type) {
            'html_pdf' => 'pdf',
            'zpl' => 'zpl',
            'text' => 'txt',
            default => 'bin',
        };
    }

    public function __construct(private readonly LabelTemplateRenderer $renderer) {}
}
