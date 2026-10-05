<?php

namespace App\Support\Integrity\Checks;

use App\Models\DocumentTemplate;
use App\Support\DocumentTemplates\TemplateDefinitionValidator;
use App\Support\DocumentTemplates\TemplateExpressionValidator;
use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class DocumentTemplateIntegrityCheck implements IntegrityCheck
{
    public function __construct(
        private readonly TemplateDefinitionValidator $validator,
        private readonly TemplateExpressionValidator $expressions,
    ) {}

    public function name(): string
    {
        return 'templates';
    }

    public function run(): IntegrityResult
    {
        $started = hrtime(true);

        if (! Schema::connection('master')->hasTable('document_templates')) {
            return new IntegrityResult(
                checked: 0,
                mismatches: [],
                durationMs: $this->elapsed($started),
                meta: ['status' => 'not_applicable'],
            );
        }

        $templates = DocumentTemplate::query()
            ->orderBy('company_id')
            ->orderBy('template_key')
            ->orderBy('revision_no')
            ->get();
        $mismatches = [];

        foreach ($templates->groupBy(fn ($template): string => $template->company_id.'|'.$template->template_key) as $scope => $group) {
            $revisionNos = $group->pluck('revision_no')->map(fn ($value): int => (int) $value)->values()->all();
            $expected = range(1, count($revisionNos));

            if ($revisionNos !== $expected) {
                $mismatches[] = [
                    'scope' => $scope,
                    'reason' => 'revision_sequence_invalid',
                    'revision_nos' => $revisionNos,
                ];
            }

            $activeCount = $group->filter(fn ($template): bool => $template->is_active)->count();
            $defaultActive = $group
                ->filter(fn ($template): bool => $template->is_active && $template->is_default)
                ->count();

            if ($activeCount > 0 && $defaultActive !== 1) {
                $mismatches[] = [
                    'scope' => $scope,
                    'reason' => 'default_active_count_invalid',
                    'default_active_count' => $defaultActive,
                ];
            }

            foreach ($group as $template) {
                if ($template->is_default && ! $template->is_active) {
                    $mismatches[] = [
                        'template_id' => $template->id,
                        'reason' => 'default_revision_inactive',
                    ];
                }

                try {
                    $definition = $this->validator->normalize($template->definition ?? []);
                    $this->expressions->validateDefinition($definition);
                } catch (Throwable $exception) {
                    $mismatches[] = [
                        'template_id' => $template->id,
                        'reason' => 'definition_or_token_contract_invalid',
                        'detail' => $exception->getMessage(),
                    ];
                }
            }
        }

        return new IntegrityResult(
            checked: $templates->count(),
            mismatches: $mismatches,
            durationMs: $this->elapsed($started),
        );
    }

    private function elapsed(int $started): int
    {
        return (int) round((hrtime(true) - $started) / 1_000_000);
    }
}
