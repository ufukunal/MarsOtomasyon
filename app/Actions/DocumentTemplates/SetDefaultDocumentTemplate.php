<?php

namespace App\Actions\DocumentTemplates;

use App\Models\DocumentTemplate;
use App\Models\User;
use App\Support\Period\PeriodContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class SetDefaultDocumentTemplate
{
    public function handle(int $templateId, ?User $actor = null): DocumentTemplate
    {
        PeriodContext::ensure();
        $actor ??= auth()->user();

        if (! $actor || ! $actor->is_active) {
            throw new AuthorizationException('Template varsayılanı için aktif kullanıcı gereklidir.');
        }

        Gate::forUser($actor)->authorize('document_templates.update');
        $companyId = (int) PeriodContext::companyId();

        return DB::connection('master')->transaction(function () use ($templateId, $companyId): DocumentTemplate {
            $template = DocumentTemplate::query()
                ->whereKey($templateId)
                ->where('company_id', $companyId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $template->is_active) {
                throw new \DomainException('Pasif template varsayılan yapılamaz.');
            }

            DB::connection('master')->select(
                'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
                ["document-template|{$companyId}|{$template->template_key}"],
            );

            $defaults = DocumentTemplate::query()
                ->where('company_id', $companyId)
                ->where('template_key', $template->template_key)
                ->where('is_default', true)
                ->where('id', '<>', $template->id)
                ->lockForUpdate()
                ->get();

            foreach ($defaults as $current) {
                $current->updateWithVersion(['is_default' => false], (int) $current->version);
            }

            if ($template->is_default) {
                return $template;
            }

            return $template->updateWithVersion(
                ['is_default' => true],
                (int) $template->version,
            );
        }, attempts: 3);
    }
}
