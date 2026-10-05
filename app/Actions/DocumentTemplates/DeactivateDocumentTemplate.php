<?php

namespace App\Actions\DocumentTemplates;

use App\Models\DocumentTemplate;
use App\Models\User;
use App\Support\Period\PeriodContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class DeactivateDocumentTemplate
{
    public function handle(int $templateId, ?User $actor = null): DocumentTemplate
    {
        PeriodContext::ensure();
        $actor ??= auth()->user();

        if (! $actor || ! $actor->is_active) {
            throw new AuthorizationException('Template pasifleştirmek için aktif kullanıcı gereklidir.');
        }

        Gate::forUser($actor)->authorize('document_templates.update');
        $companyId = (int) PeriodContext::companyId();

        return DB::connection('master')->transaction(function () use ($templateId, $companyId): DocumentTemplate {
            $template = DocumentTemplate::query()
                ->whereKey($templateId)
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->firstOrFail();

            DB::connection('master')->select(
                'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
                ["document-template|{$companyId}|{$template->template_key}"],
            );

            if (! $template->is_active) {
                return $template;
            }

            $wasDefault = $template->is_default;
            $updated = $template->updateWithVersion(
                ['is_active' => false, 'is_default' => false],
                (int) $template->version,
            );

            if ($wasDefault) {
                $replacement = DocumentTemplate::query()
                    ->where('company_id', $companyId)
                    ->where('template_key', $template->template_key)
                    ->where('is_active', true)
                    ->where('id', '<>', $template->id)
                    ->orderByDesc('revision_no')
                    ->lockForUpdate()
                    ->first();

                if ($replacement && ! $replacement->is_default) {
                    $replacement->updateWithVersion(
                        ['is_default' => true],
                        (int) $replacement->version,
                    );
                }
            }

            return $updated;
        }, attempts: 3);
    }
}
