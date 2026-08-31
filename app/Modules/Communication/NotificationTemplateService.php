<?php

namespace App\Modules\Communication;

use DomainException;
use Illuminate\Support\Facades\DB;

final class NotificationTemplateService
{
    /** @param list<string> $variables */
    public function store(
        int $companyId,
        string $key,
        string $channel,
        string $name,
        ?string $subject,
        string $body,
        array $variables,
        ?int $userId = null,
    ): int {
        $key = mb_strtolower(trim($key));
        $channel = mb_strtolower(trim($channel));
        $name = trim($name);
        $subject = $subject === null || trim($subject) === '' ? null : trim($subject);
        $body = trim($body);
        $variables = array_values(array_unique(array_map(static fn (string $value): string => trim($value), $variables)));
        sort($variables);

        if (preg_match('/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/D', $key) !== 1) {
            throw new DomainException('Template key must be canonical.');
        }
        if (! in_array($channel, ['email', 'sms', 'whatsapp'], true)) {
            throw new DomainException('Unsupported notification channel.');
        }
        if ($name === '' || $body === '') {
            throw new DomainException('Template name and body are required.');
        }
        foreach ($variables as $variable) {
            if (preg_match('/^[a-z][a-z0-9_.-]*$/D', $variable) !== 1) {
                throw new DomainException('Template variable name is invalid.');
            }
        }

        return DB::transaction(function () use ($companyId, $key, $channel, $name, $subject, $body, $variables, $userId): int {
            $template = DB::table('notification_templates')
                ->where('company_id', $companyId)
                ->where('key', $key)
                ->where('channel', $channel)
                ->lockForUpdate()
                ->first();

            if ($template === null) {
                $templateId = (int) DB::table('notification_templates')->insertGetId([
                    'company_id' => $companyId,
                    'key' => $key,
                    'channel' => $channel,
                    'name' => $name,
                    'status' => 'active',
                    'subject' => $subject,
                    'body' => $body,
                    'current_version' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->insertVersion($companyId, $templateId, 1, $name, $subject, $body, $variables, $userId);

                return $templateId;
            }

            $current = (int) $template->current_version;
            $version = DB::table('notification_template_versions')
                ->where('company_id', $companyId)
                ->where('template_id', $template->id)
                ->where('version', $current)
                ->first();
            $storedVariables = [];
            if ($version !== null) {
                $decoded = json_decode((string) $version->variables, true, flags: JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $storedVariables = array_values(array_map(static fn (mixed $value): string => (string) $value, $decoded));
                    sort($storedVariables);
                }
            }
            if (
                (string) $template->name === $name
                && ($template->subject === null ? null : (string) $template->subject) === $subject
                && (string) $template->body === $body
                && $storedVariables === $variables
            ) {
                return (int) $template->id;
            }

            $next = $current + 1;
            $this->insertVersion($companyId, (int) $template->id, $next, $name, $subject, $body, $variables, $userId);
            DB::table('notification_templates')->where('id', $template->id)->update([
                'name' => $name,
                'subject' => $subject,
                'body' => $body,
                'current_version' => $next,
                'updated_at' => now(),
            ]);

            return (int) $template->id;
        });
    }

    /**
     * @param  array<string, scalar|null>  $variables
     * @return array{subject:?string,body:string}
     */
    public function preview(string $subject, string $body, array $variables): array
    {
        return [
            'subject' => $subject === '' ? null : $this->render($subject, $variables),
            'body' => $this->render($body, $variables),
        ];
    }

    /** @param array<string, scalar|null> $variables */
    public function render(string $template, array $variables): string
    {
        $replace = [];
        foreach ($variables as $key => $value) {
            $replace['{{'.$key.'}}'] = $value === null ? '' : (string) $value;
        }
        $rendered = strtr($template, $replace);
        if (preg_match('/\{\{[a-zA-Z0-9_.-]+\}\}/', $rendered) === 1) {
            throw new DomainException('Template contains unresolved variables.');
        }

        return $rendered;
    }

    public function activeVersion(int $companyId, string $key, string $channel): NotificationTemplateVersionView
    {
        $key = mb_strtolower(trim($key));
        $channel = mb_strtolower(trim($channel));
        $template = DB::table('notification_templates')
            ->where('company_id', $companyId)
            ->where('key', $key)
            ->where('channel', $channel)
            ->where('status', 'active')
            ->first();
        if ($template === null) {
            throw new DomainException('Active notification template not found.');
        }
        $versionNo = (int) ($template->current_version ?? 1);
        $version = DB::table('notification_template_versions')
            ->where('company_id', $companyId)
            ->where('template_id', $template->id)
            ->where('version', $versionNo)
            ->first();

        return new NotificationTemplateVersionView(
            templateId: (int) $template->id,
            version: $versionNo,
            subject: $version === null || $version->subject === null
                ? ($template->subject === null ? null : (string) $template->subject)
                : (string) $version->subject,
            body: $version === null ? (string) $template->body : (string) $version->body,
        );
    }

    /** @param list<string> $variables */
    private function insertVersion(int $companyId, int $templateId, int $version, string $name, ?string $subject, string $body, array $variables, ?int $userId): void
    {
        DB::table('notification_template_versions')->insert([
            'company_id' => $companyId,
            'template_id' => $templateId,
            'version' => $version,
            'name' => $name,
            'subject' => $subject,
            'body' => $body,
            'variables' => json_encode($variables, JSON_THROW_ON_ERROR),
            'created_by_user_id' => $userId,
            'created_at' => now(),
        ]);
    }
}
