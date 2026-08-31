<?php

namespace App\Modules\Communication\Http;

use App\Modules\Communication\NotificationTemplateService;
use App\Modules\Communication\SystemIntegrationService;
use App\Modules\Core\Company\ActiveCompanyContext;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final readonly class SystemIntegrationController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private SystemIntegrationService $integrations,
        private NotificationTemplateService $templates,
    ) {}

    public function index(): View
    {
        $companyId = $this->companyId();

        return view('settings.integrations.index', [
            'integrations' => $this->integrations->summaries($companyId),
            'templates' => DB::table('notification_templates')->where('company_id', $companyId)->orderBy('channel')->orderBy('key')->get(),
            'attempts' => DB::table('notification_provider_attempts')->where('company_id', $companyId)->latest('id')->limit(30)->get(),
        ]);
    }

    public function update(Request $request, string $family): RedirectResponse
    {
        $validated = $request->validate([
            'provider_key' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/'],
            'endpoint_url' => ['nullable', 'url', 'max:512'],
            'settings_json' => ['nullable', 'json'],
            'credentials_json' => ['nullable', 'json'],
            'is_enabled' => ['required', 'boolean'],
        ]);
        $settings = isset($validated['settings_json']) && trim((string) $validated['settings_json']) !== ''
            ? json_decode((string) $validated['settings_json'], true, flags: JSON_THROW_ON_ERROR)
            : [];
        $credentials = isset($validated['credentials_json']) && trim((string) $validated['credentials_json']) !== ''
            ? json_decode((string) $validated['credentials_json'], true, flags: JSON_THROW_ON_ERROR)
            : null;
        if (! is_array($settings) || ($credentials !== null && ! is_array($credentials))) {
            abort(422, 'Integration settings or credentials are invalid.');
        }

        /** @var array<string, scalar|null>|null $credentialScalars */
        $credentialScalars = null;
        if ($credentials !== null) {
            $credentialScalars = [];
            foreach ($credentials as $key => $value) {
                if (! is_string($key) || (! is_scalar($value) && $value !== null)) {
                    abort(422, 'Credentials must be a flat scalar object.');
                }
                $credentialScalars[$key] = $value;
            }
        }

        $this->integrations->save(
            $this->companyId(),
            $family,
            $validated['provider_key'] ?? null,
            $validated['endpoint_url'] ?? null,
            $settings,
            $credentialScalars,
            (bool) $validated['is_enabled'],
        );

        return back()->with('status', 'Entegrasyon ayarı kaydedildi; credential değerleri tekrar gösterilmez.');
    }

    public function validateConfiguration(string $family): RedirectResponse
    {
        try {
            $this->integrations->validateConfiguration($this->companyId(), $family);
        } catch (DomainException $exception) {
            return back()->withErrors(['integration' => $exception->getMessage()]);
        }

        return back()->with('status', 'Yapılandırma doğrulandı. Bu sonuç production provider bağlantı kanıtı değildir.');
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:96', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/'],
            'channel' => ['required', Rule::in(['email', 'sms', 'whatsapp'])],
            'name' => ['required', 'string', 'max:160'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'variables' => ['nullable', 'string', 'max:2000'],
        ]);
        $variables = array_values(array_filter(array_map('trim', explode(',', (string) ($validated['variables'] ?? '')))));
        $userId = $request->user()?->getAuthIdentifier();
        $this->templates->store(
            $this->companyId(),
            (string) $validated['key'],
            (string) $validated['channel'],
            (string) $validated['name'],
            $validated['subject'] ?? null,
            (string) $validated['body'],
            $variables,
            is_numeric($userId) ? (int) $userId : null,
        );

        return back()->with('status', 'Bildirim şablonunun yeni immutable versiyonu kaydedildi.');
    }

    public function preview(Request $request): View
    {
        $validated = $request->validate([
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'variables_json' => ['nullable', 'json'],
        ]);
        $variables = isset($validated['variables_json']) && trim((string) $validated['variables_json']) !== ''
            ? json_decode((string) $validated['variables_json'], true, flags: JSON_THROW_ON_ERROR)
            : [];
        if (! is_array($variables)) {
            abort(422, 'Preview variables are invalid.');
        }

        /** @var array<string, scalar|null> $scalarVariables */
        $scalarVariables = [];
        foreach ($variables as $key => $value) {
            if (! is_string($key) || (! is_scalar($value) && $value !== null)) {
                abort(422, 'Preview variables must be scalar.');
            }
            $scalarVariables[$key] = $value;
        }
        $preview = $this->templates->preview((string) ($validated['subject'] ?? ''), (string) $validated['body'], $scalarVariables);

        return view('settings.integrations.preview', ['preview' => $preview]);
    }

    private function companyId(): int
    {
        return (int) $this->companyContext->requireCompany()->getKey();
    }
}
