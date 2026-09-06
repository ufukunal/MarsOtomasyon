<?php

namespace App\Modules\Core\Management;

use App\Foundation\Features\FeatureKey;
use App\Foundation\Features\FeatureRegistry;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Files\CompanyFileManager;
use App\Modules\Core\Models\FileAsset;
use App\Modules\Core\Preview\CadPreviewService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class CadPreviewController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private CompanyFileManager $files,
        private CadPreviewService $cad,
        private FeatureRegistry $features,
    ) {}

    public function show(Request $request, int $attachment): View
    {
        $this->assertEnabled();
        $record = $this->files->attachment($attachment);
        abort_if($record->isDetached(), 404);

        $asset = $record->fileAsset;
        abort_unless($asset instanceof FileAsset, 404);

        $extension = strtolower(ltrim((string) $asset->client_extension, '.'));
        $provider = strtolower((string) $request->query('provider', $extension === 'dwg' ? 'aps' : 'local'));
        if (! in_array($provider, ['local', 'aps'], true)) {
            abort(404);
        }

        return view('settings.files.cad', [
            'attachment' => $record,
            'asset' => $asset,
            'extension' => $extension,
            'provider' => $provider,
            'job' => $this->cad->latestForAttachment($this->companyId(), $attachment, $provider),
        ]);
    }

    public function request(Request $request, int $attachment): RedirectResponse
    {
        $this->assertEnabled();
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:local,aps'],
        ]);

        $job = $this->cad->requestPreview($this->companyId(), $attachment, (string) $validated['provider']);

        return redirect()
            ->route('settings.files.cad.show', ['attachment' => $attachment, 'provider' => $job->provider])
            ->with('status', $job->status === 'failed'
                ? 'CAD/3D önizleme isteği kontrollü hata ile sonuçlandı.'
                : 'CAD/3D önizleme isteği işlendi.');
    }

    public function refresh(int $attachment, int $job): JsonResponse
    {
        $this->assertEnabled();
        $record = $this->files->attachment($attachment);
        abort_if($record->isDetached(), 404);

        $updated = $this->cad->refreshPreview($this->companyId(), $job);
        abort_unless((int) $updated->attachment_id === $attachment, 404);

        return response()->json([
            'status' => $updated->status,
            'failure_code' => $updated->failure_code,
        ]);
    }

    public function rebuild(int $attachment, int $job): RedirectResponse
    {
        $this->assertEnabled();
        $record = $this->files->attachment($attachment);
        abort_if($record->isDetached(), 404);

        $rebuilt = $this->cad->rebuildPreview($this->companyId(), $job);
        abort_unless((int) $rebuilt->attachment_id === $attachment, 404);

        return redirect()
            ->route('settings.files.cad.show', ['attachment' => $attachment, 'provider' => $rebuilt->provider])
            ->with('status', 'CAD/3D derivative yeniden oluşturma isteği işlendi.');
    }

    public function source(int $attachment): StreamedResponse
    {
        $this->assertEnabled();
        $record = $this->files->attachment($attachment);
        abort_if($record->isDetached(), 404);

        $asset = $record->fileAsset;
        abort_unless($asset instanceof FileAsset, 404);
        $extension = strtolower(ltrim((string) $asset->client_extension, '.'));
        abort_unless(in_array($extension, ['dxf', 'obj'], true), 404);
        abort_if($asset->archived_at !== null || $asset->quarantined_at !== null, 404);

        $disk = Storage::disk((string) $asset->storage_disk);
        abort_unless($disk->exists((string) $asset->storage_key), 410);

        $stream = $disk->readStream((string) $asset->storage_key);
        if (! is_resource($stream)) {
            throw new LogicException('CAD source stream could not be opened.');
        }

        return response()->stream(
            static function () use ($stream): void {
                fpassthru($stream);
                fclose($stream);
            },
            200,
            [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Content-Disposition' => 'inline; filename="'.addslashes((string) $asset->original_name).'"',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store',
            ],
        );
    }

    public function viewerToken(int $attachment, int $job): JsonResponse
    {
        $this->assertEnabled();
        $record = $this->files->attachment($attachment);
        abort_if($record->isDetached(), 404);

        try {
            $token = $this->cad->viewerToken($this->companyId(), $job);
        } catch (DomainException) {
            abort(409, 'CAD viewer tokenı henüz kullanılamıyor.');
        }

        abort_if($token === null, 404);

        return response()->json($token, headers: ['Cache-Control' => 'private, no-store']);
    }

    public function policy(): View
    {
        $this->assertEnabled();

        return view('settings.files.cad-policy', [
            'policy' => $this->cad->policyFor($this->companyId(), 'aps'),
        ]);
    }

    public function updatePolicy(Request $request): RedirectResponse
    {
        $this->assertEnabled();
        $validated = $request->validate([
            'cloud_upload_enabled' => ['nullable', 'boolean'],
            'max_file_size_mb' => ['required', 'integer', 'min:1', 'max:50'],
            'timeout_seconds' => ['required', 'integer', 'min:30', 'max:900'],
            'retention_days' => ['required', 'integer', 'min:1', 'max:7'],
        ]);

        $this->cad->configurePolicy(
            $this->companyId(),
            'aps',
            $request->boolean('cloud_upload_enabled'),
            ((int) $validated['max_file_size_mb']) * 1024 * 1024,
            (int) $validated['timeout_seconds'],
            (int) $validated['retention_days'],
        );

        return redirect()->route('settings.files.cad.policy')
            ->with('status', 'CAD/3D cloud upload politikası güncellendi.');
    }

    private function assertEnabled(): void
    {
        abort_unless($this->features->enabled(FeatureKey::Cad3dViewer), 404);
    }

    private function companyId(): int
    {
        $id = $this->companyContext->requireCompany()->getKey();

        return is_int($id) ? $id : throw new LogicException('CAD preview requires a persisted company.');
    }
}
