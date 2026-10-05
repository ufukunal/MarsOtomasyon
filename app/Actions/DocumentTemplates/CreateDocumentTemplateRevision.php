<?php

namespace App\Actions\DocumentTemplates;

use App\Models\DocumentTemplate;
use App\Models\User;
use App\Support\DocumentTemplates\TemplateDefinitionValidator;
use App\Support\DocumentTemplates\TemplateExpressionValidator;
use App\Support\Period\PeriodContext;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class CreateDocumentTemplateRevision
{
    public function __construct(
        private readonly TemplateDefinitionValidator $validator,
        private readonly TemplateExpressionValidator $expressions,
    ) {}

    /** @param array<string,mixed> $definition */
    public function handle(
        string $templateKey,
        string $name,
        string $renderType,
        array $definition,
        ?string $paperCode = null,
        string|int|float|null $widthMm = null,
        string|int|float|null $heightMm = null,
        bool $makeDefault = false,
        ?User $actor = null,
    ): DocumentTemplate {
        PeriodContext::ensure();
        $actor ??= auth()->user();

        if (! $actor || ! $actor->is_active) {
            throw new AuthorizationException('Template revizyonu için aktif kullanıcı gereklidir.');
        }

        Gate::forUser($actor)->authorize('document_templates.update');

        $companyId = PeriodContext::companyId();

        if (! $companyId) {
            throw new DomainException('Template revizyonu şirket bağlamı gerektirir.');
        }

        $templateKey = trim($templateKey);
        $name = trim($name);
        $renderType = trim($renderType);
        $paperCode = $paperCode !== null ? trim($paperCode) : null;

        if ($templateKey === '' || mb_strlen($templateKey) > 120 || ! preg_match('/^[a-z0-9._-]+$/D', $templateKey)) {
            throw new DomainException('Template key 1-120 karakter ve yalnız küçük harf/rakam/._- içermelidir.');
        }

        if ($name === '' || mb_strlen($name) > 160) {
            throw new DomainException('Template adı 1-160 karakter olmalıdır.');
        }

        if (! in_array($renderType, ['html_pdf', 'zpl', 'text'], true)) {
            throw new DomainException('Template render_type geçersiz.');
        }

        $normalized = $this->validator->normalize($definition);
        $this->expressions->validateDefinition($normalized);
        $lockKey = "document-template|{$companyId}|{$templateKey}";

        return DB::connection('master')->transaction(function () use (
            $companyId,
            $templateKey,
            $name,
            $renderType,
            $normalized,
            $paperCode,
            $widthMm,
            $heightMm,
            $makeDefault,
            $actor,
            $lockKey,
        ): DocumentTemplate {
            DB::connection('master')->select(
                'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
                [$lockKey],
            );

            $revisions = DocumentTemplate::query()
                ->where('company_id', $companyId)
                ->where('template_key', $templateKey)
                ->lockForUpdate()
                ->get();

            $revisionNo = ((int) $revisions->max('revision_no')) + 1;
            $activeDefault = $revisions->first(
                fn (DocumentTemplate $item): bool => $item->is_active && $item->is_default,
            );
            $shouldDefault = $makeDefault || $activeDefault === null;

            if ($shouldDefault && $activeDefault !== null) {
                $activeDefault->updateWithVersion(
                    ['is_default' => false],
                    (int) $activeDefault->version,
                );
            }

            return DocumentTemplate::query()->create([
                'company_id' => $companyId,
                'template_key' => $templateKey,
                'name' => $name,
                'revision_no' => $revisionNo,
                'render_type' => $renderType,
                'paper_code' => $paperCode !== '' ? $paperCode : null,
                'width_mm' => $this->normalizeDimension($widthMm, 'width_mm'),
                'height_mm' => $this->normalizeDimension($heightMm, 'height_mm'),
                'definition' => $normalized,
                'is_active' => true,
                'is_default' => $shouldDefault,
                'created_by' => $actor->id,
            ]);
        }, attempts: 3);
    }

    private function normalizeDimension(string|int|float|null $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric((string) $value)
            || bccomp((string) $value, '0', 2) <= 0
            || bccomp((string) $value, '1000', 2) > 0) {
            throw new DomainException("{$field} 0-1000 mm aralığında olmalıdır.");
        }

        return bcadd((string) $value, '0', 2);
    }
}
