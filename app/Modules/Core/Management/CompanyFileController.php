<?php

namespace App\Modules\Core\Management;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AttachmentTargetType;
use App\Modules\Core\Files\CompanyFileManager;
use App\Modules\Core\Models\Attachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class CompanyFileController
{
    public function __construct(
        private ActiveCompanyContext $companyContext,
        private CompanyFileManager $files,
    ) {}

    public function index(): View
    {
        $companyId = $this->companyId();

        return view('settings.files.index', [
            'attachments' => Attachment::query()
                ->where('company_id', $companyId)
                ->where('attachable_type', AttachmentTargetType::Company->value)
                ->where('attachable_id', $companyId)
                ->with(['fileAsset', 'attachedBy', 'detachedBy'])
                ->orderByDesc('attached_at')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('settings.files.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:51200'],
            'label' => ['nullable', 'string', 'max:160'],
        ]);

        $upload = $request->file('file');
        if (! $upload instanceof UploadedFile) {
            abort(422, 'Dosya yükleme isteği geçersiz.');
        }

        $attachment = $this->files->upload($upload, isset($validated['label']) ? (string) $validated['label'] : null);

        return redirect()->route('settings.files.show', $attachment->getKey())
            ->with('status', 'Dosya private storage alanına yüklendi.');
    }

    public function show(int $attachment): View
    {
        return view('settings.files.show', [
            'attachment' => $this->files->attachment($attachment),
        ]);
    }

    public function download(int $attachment): StreamedResponse
    {
        return $this->files->download($attachment);
    }

    public function detach(int $attachment): RedirectResponse
    {
        $detached = $this->files->detach($attachment);

        return redirect()->route('settings.files.show', $detached->getKey())
            ->with('status', 'Dosya bağlantısı kaldırıldı. Orijinal dosya arşivde korunuyor.');
    }

    private function companyId(): int
    {
        return (int) $this->companyContext->requireCompany()->getKey();
    }
}
