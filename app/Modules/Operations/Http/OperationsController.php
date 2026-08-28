<?php

namespace App\Modules\Operations\Http;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Operations\AutomationService;
use App\Modules\Operations\BackupManager;
use App\Modules\Operations\ChannelService;
use App\Modules\Operations\Jobs\DeliverNotification;
use App\Modules\Operations\Jobs\ExecuteAutomationRun;
use App\Modules\Operations\Jobs\ProcessIntegrationEvent;
use App\Modules\Operations\Jobs\ProcessIntegrationSync;
use App\Modules\Operations\OperationsHealth;
use App\Modules\Operations\SecurityCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class OperationsController
{
    public function __construct(private ActiveCompanyContext $companyContext) {}

    public function index(OperationsHealth $health): View
    {
        $companyId = $this->companyId();

        return view('operations.index', [
            'health' => $health->snapshot(),
            'connections' => DB::table('integration_connections')->where('company_id', $companyId)->orderBy('provider')->orderBy('name')->get(),
            'integrationEvents' => DB::table('integration_events')->where('company_id', $companyId)->latest('id')->limit(20)->get(),
            'syncEffects' => DB::table('integration_sync_effects')->where('company_id', $companyId)->latest('id')->limit(20)->get(),
            'templates' => DB::table('notification_templates')->where('company_id', $companyId)->orderBy('key')->get(),
            'deliveries' => DB::table('notification_deliveries')->where('company_id', $companyId)->latest('id')->limit(20)->get(),
            'rules' => DB::table('automation_rules')->where('company_id', $companyId)->orderBy('priority')->get(),
            'runs' => DB::table('automation_runs')->where('company_id', $companyId)->latest('id')->limit(20)->get(),
            'ipRules' => DB::table('security_ip_rules')->where('company_id', $companyId)->orderBy('action')->get(),
            'securityEvents' => DB::table('security_events')->where('company_id', $companyId)->latest('id')->limit(20)->get(),
            'backups' => DB::table('backup_artifacts')->latest('created_at')->limit(20)->get(),
        ]);
    }

    public function storeConnection(Request $request, ChannelService $channels, SecurityCenter $security): RedirectResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'in:woocommerce,trendyol'],
            'name' => ['required', 'string', 'max:96'],
            'base_url' => ['nullable', 'url', 'max:512'],
            'credentials' => ['required', 'array'],
            'webhook_secret' => ['required', 'string', 'min:16', 'max:512'],
        ]);
        $companyId = $this->companyId();
        $channels->createConnection($companyId, (string) $validated['provider'], (string) $validated['name'], $validated['base_url'] ?? null, $validated['credentials'], (string) $validated['webhook_secret']);
        $security->record($companyId, $this->userId($request), 'integration.connection_created', 'info', $request->ip(), $request->userAgent(), ['provider' => $validated['provider'], 'name' => $validated['name']]);

        return back()->with('status', 'Entegrasyon bağlantısı oluşturuldu.');
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:96', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/'],
            'channel' => ['required', 'in:email,sms,whatsapp'],
            'name' => ['required', 'string', 'max:160'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
        ]);
        DB::table('notification_templates')->updateOrInsert(
            ['company_id' => $this->companyId(), 'key' => $validated['key'], 'channel' => $validated['channel']],
            ['name' => $validated['name'], 'status' => 'active', 'subject' => $validated['subject'] ?? null, 'body' => $validated['body'], 'created_at' => now(), 'updated_at' => now()],
        );

        return back()->with('status', 'Bildirim şablonu kaydedildi.');
    }

    public function storeAutomationRule(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:96', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/'],
            'name' => ['required', 'string', 'max:160'],
            'event_type' => ['required', 'string', 'max:96'],
            'conditions' => ['nullable', 'array'],
            'conditions_json' => ['nullable', 'json'],
            'action_type' => ['required', 'in:notify,integration_sync,security_event'],
            'action_payload' => ['nullable', 'array'],
            'action_payload_json' => ['nullable', 'json'],
            'requires_approval' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'integer', 'between:-32768,32767'],
        ]);
        $conditions = $validated['conditions'] ?? (isset($validated['conditions_json']) ? json_decode((string) $validated['conditions_json'], true, flags: JSON_THROW_ON_ERROR) : []);
        $actionPayload = $validated['action_payload'] ?? (isset($validated['action_payload_json']) ? json_decode((string) $validated['action_payload_json'], true, flags: JSON_THROW_ON_ERROR) : null);
        if (! is_array($conditions) || ! is_array($actionPayload)) {
            abort(422, 'Otomasyon koşulları veya aksiyon payload geçersiz.');
        }
        DB::table('automation_rules')->updateOrInsert(
            ['company_id' => $this->companyId(), 'key' => $validated['key']],
            [
                'name' => $validated['name'], 'event_type' => $validated['event_type'],
                'conditions' => json_encode($conditions, JSON_THROW_ON_ERROR),
                'action_type' => $validated['action_type'], 'action_payload' => json_encode($actionPayload, JSON_THROW_ON_ERROR),
                'requires_approval' => (bool) ($validated['requires_approval'] ?? false), 'is_enabled' => true,
                'priority' => (int) ($validated['priority'] ?? 100), 'created_at' => now(), 'updated_at' => now(),
            ],
        );

        return back()->with('status', 'Otomasyon kuralı kaydedildi.');
    }

    public function approveAutomation(Request $request, int $run, AutomationService $automation): RedirectResponse
    {
        $automation->approve($this->companyId(), $run, $this->requiredUserId($request));

        return back()->with('status', 'Otomasyon onaylandı.');
    }

    public function rejectAutomation(Request $request, int $run, AutomationService $automation): RedirectResponse
    {
        $automation->reject($this->companyId(), $run, $this->requiredUserId($request));

        return back()->with('status', 'Otomasyon reddedildi.');
    }

    public function storeIpRule(Request $request, SecurityCenter $security): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:allow,deny'],
            'cidr' => ['required', 'string', 'max:64'],
            'label' => ['nullable', 'string', 'max:160'],
        ]);
        $companyId = $this->companyId();
        DB::table('security_ip_rules')->insert([
            'company_id' => $companyId, 'action' => $validated['action'], 'cidr' => $validated['cidr'], 'label' => $validated['label'] ?? null,
            'is_active' => true, 'created_by_user_id' => $this->userId($request), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $security->record($companyId, $this->userId($request), 'security.ip_rule_created', 'warning', $request->ip(), $request->userAgent(), ['action' => $validated['action'], 'cidr' => $validated['cidr']]);

        return back()->with('status', 'IP güvenlik kuralı eklendi.');
    }

    public function destroyIpRule(Request $request, int $rule, SecurityCenter $security): RedirectResponse
    {
        $companyId = $this->companyId();
        $deleted = DB::table('security_ip_rules')->where('company_id', $companyId)->where('id', $rule)->delete();
        abort_if($deleted === 0, 404);
        $security->record($companyId, $this->userId($request), 'security.ip_rule_deleted', 'warning', $request->ip(), $request->userAgent(), ['rule_id' => $rule]);

        return back()->with('status', 'IP güvenlik kuralı silindi.');
    }

    public function createBackup(Request $request, BackupManager $backups, SecurityCenter $security): RedirectResponse
    {
        $id = $backups->create($this->userId($request));
        $security->record($this->companyId(), $this->userId($request), 'backup.created', 'warning', $request->ip(), $request->userAgent(), ['backup_id' => $id]);

        return back()->with('status', 'Şifreli yedek oluşturuldu: '.$id);
    }

    public function verifyBackup(string $backup, BackupManager $backups): RedirectResponse
    {
        return back()->with('status', $backups->verify($backup) ? 'Yedek checksum doğrulandı.' : 'Yedek checksum doğrulanamadı.');
    }

    public function restoreBackup(Request $request, string $backup, BackupManager $backups, SecurityCenter $security): RedirectResponse
    {
        $request->validate(['confirm' => ['required', 'in:RESTORE']]);
        $security->record($this->companyId(), $this->userId($request), 'backup.restore_started', 'critical', $request->ip(), $request->userAgent(), ['backup_id' => $backup]);
        $backups->restore($backup, $this->userId($request), true);

        return back()->with('status', 'Restore tamamlandı ve safety backup alındı.');
    }

    public function retry(Request $request, string $type, int $id): RedirectResponse
    {
        $companyId = $this->companyId();
        match ($type) {
            'event' => $this->retryRow('integration_events', $companyId, $id, 'received', fn () => ProcessIntegrationEvent::dispatch($id)),
            'sync' => $this->retryRow('integration_sync_effects', $companyId, $id, 'queued', fn () => ProcessIntegrationSync::dispatch($id)),
            'notification' => $this->retryRow('notification_deliveries', $companyId, $id, 'queued', fn () => DeliverNotification::dispatch($id)),
            'automation' => $this->retryAutomation($companyId, $id),
            default => abort(404),
        };

        return back()->with('status', 'İş tekrar kuyruğa alındı.');
    }

    public function exportCsv(string $type): StreamedResponse
    {
        $companyId = $this->companyId();
        $map = [
            'integration-events' => ['integration_events', ['id', 'external_event_id', 'event_type', 'status', 'attempts', 'created_at']],
            'notification-deliveries' => ['notification_deliveries', ['id', 'channel', 'recipient', 'status', 'attempts', 'sent_at', 'created_at']],
            'automation-runs' => ['automation_runs', ['id', 'rule_id', 'trigger_key', 'status', 'created_at', 'finished_at']],
            'security-events' => ['security_events', ['id', 'event_type', 'severity', 'ip_address', 'created_at']],
        ];
        abort_unless(isset($map[$type]), 404);
        [$table, $columns] = $map[$type];

        return response()->streamDownload(function () use ($companyId, $table, $columns): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }
            fputcsv($handle, $columns);
            DB::table($table)->where('company_id', $companyId)->orderBy('id')->chunk(500, function ($rows) use ($handle, $columns): void {
                foreach ($rows as $row) {
                    fputcsv($handle, array_map(fn (string $column): mixed => $row->{$column}, $columns));
                }
            });
            fclose($handle);
        }, 'mars-'.$type.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function importCsv(Request $request, string $type): RedirectResponse
    {
        $validated = $request->validate(['file' => ['required', 'file', 'mimetypes:text/plain,text/csv,application/csv', 'max:2048']]);
        abort_unless(in_array($type, ['notification-templates', 'automation-rules'], true), 404);
        $path = $validated['file']->getRealPath();
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            abort(422, 'CSV açılamadı.');
        }
        $headerRow = fgetcsv($handle);
        if ($headerRow === false) {
            fclose($handle);
            abort(422, 'CSV başlığı yok.');
        }
        $headers = array_map(static fn (?string $value): string => trim($value ?? ''), $headerRow);
        if ($headers === [] || in_array('', $headers, true) || count(array_unique($headers)) !== count($headers)) {
            fclose($handle);
            abort(422, 'CSV başlıkları boş veya tekrarlı olamaz.');
        }
        $count = 0;
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) {
                continue;
            }
            $values = array_map(static fn (?string $value): string => $value ?? '', $row);
            $data = array_combine($headers, $values);
            $type === 'notification-templates' ? $this->importTemplateRow($data) : $this->importAutomationRow($data);
            $count++;
        }
        fclose($handle);

        return back()->with('status', $count.' CSV satırı içe aktarıldı.');
    }

    private function companyId(): int
    {
        return (int) $this->companyContext->requireCompany()->getKey();
    }

    private function userId(Request $request): ?int
    {
        $id = $request->user()?->getAuthIdentifier();

        return is_numeric($id) ? (int) $id : null;
    }

    private function requiredUserId(Request $request): int
    {
        return $this->userId($request) ?? abort(401);
    }

    private function retryAutomation(int $companyId, int $id): null
    {
        $updated = DB::table('automation_runs')->where('company_id', $companyId)->where('id', $id)->where('status', 'failed')->update([
            'status' => 'queued', 'started_at' => null, 'finished_at' => null, 'last_error' => null, 'updated_at' => now(),
        ]);
        abort_if($updated === 0, 404);
        ExecuteAutomationRun::dispatch($id);

        return null;
    }

    private function retryRow(string $table, int $companyId, int $id, string $status, \Closure $dispatch): null
    {
        $updated = DB::table($table)->where('company_id', $companyId)->where('id', $id)->update([
            'status' => $status, 'available_at' => now(), 'last_error' => null, 'updated_at' => now(),
        ]);
        abort_if($updated === 0, 404);
        $dispatch();

        return null;
    }

    /** @param array<string,string> $row */
    private function importTemplateRow(array $row): void
    {
        foreach (['key', 'channel', 'name', 'body'] as $required) {
            if (($row[$required] ?? '') === '') {
                return;
            }
        }
        if (! in_array($row['channel'], ['email', 'sms', 'whatsapp'], true)) {
            return;
        }
        DB::table('notification_templates')->updateOrInsert(
            ['company_id' => $this->companyId(), 'key' => $row['key'], 'channel' => $row['channel']],
            ['name' => $row['name'], 'status' => 'active', 'subject' => $row['subject'] ?? null, 'body' => $row['body'], 'created_at' => now(), 'updated_at' => now()],
        );
    }

    /** @param array<string,string> $row */
    private function importAutomationRow(array $row): void
    {
        foreach (['key', 'name', 'event_type', 'action_type', 'action_payload'] as $required) {
            if (($row[$required] ?? '') === '') {
                return;
            }
        }
        if (! in_array($row['action_type'], ['notify', 'integration_sync', 'security_event'], true)) {
            return;
        }
        $conditions = json_decode($row['conditions'] ?? '{}', true);
        $action = json_decode($row['action_payload'], true);
        if (! is_array($conditions) || ! is_array($action)) {
            return;
        }
        DB::table('automation_rules')->updateOrInsert(
            ['company_id' => $this->companyId(), 'key' => $row['key']],
            [
                'name' => $row['name'], 'event_type' => $row['event_type'], 'conditions' => json_encode($conditions, JSON_THROW_ON_ERROR),
                'action_type' => $row['action_type'], 'action_payload' => json_encode($action, JSON_THROW_ON_ERROR),
                'requires_approval' => filter_var($row['requires_approval'] ?? false, FILTER_VALIDATE_BOOL), 'is_enabled' => true,
                'priority' => (int) ($row['priority'] ?? 100), 'created_at' => now(), 'updated_at' => now(),
            ],
        );
    }
}
