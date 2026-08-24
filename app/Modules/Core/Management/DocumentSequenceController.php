<?php

namespace App\Modules\Core\Management;

use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\DocumentType;
use App\Modules\Core\Models\DocumentSequence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class DocumentSequenceController
{
    public function __construct(private readonly ActiveCompanyContext $companyContext) {}

    public function index(): View
    {
        return view('settings.numbering.index', [
            'sequences' => DocumentSequence::query()
                ->where('company_id', $this->companyId())
                ->orderBy('document_type')
                ->orderBy('series_code')
                ->get(),
        ]);
    }

    public function show(int $sequence): View
    {
        return view('settings.numbering.show', [
            'sequence' => $this->sequence($sequence),
        ]);
    }

    public function create(): View
    {
        return view('settings.numbering.create', [
            'documentTypes' => DocumentType::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', Rule::enum(DocumentType::class)],
            'series_code' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9._-]+$/'],
            'prefix' => ['nullable', 'string', 'max:32'],
            'padding' => ['required', 'integer', 'min:1', 'max:18'],
            'next_value' => ['required', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ]);

        $documentType = DocumentType::from((string) $validated['document_type']);
        $seriesCode = mb_strtolower(trim((string) $validated['series_code']));
        $this->assertIdentityAvailable($documentType, $seriesCode);

        $sequence = DocumentSequence::query()->create([
            'company_id' => $this->companyId(),
            'document_type' => $documentType,
            'series_code' => $seriesCode,
            'prefix' => (string) ($validated['prefix'] ?? ''),
            'padding' => (int) $validated['padding'],
            'next_value' => (int) $validated['next_value'],
            'is_active' => (bool) $validated['is_active'],
        ]);

        return redirect()->route('settings.numbering.show', $sequence->getKey())
            ->with('status', 'Numara serisi oluşturuldu.');
    }

    public function edit(int $sequence): View
    {
        return view('settings.numbering.edit', [
            'sequence' => $this->sequence($sequence),
        ]);
    }

    public function update(Request $request, int $sequence): RedirectResponse
    {
        $sequence = $this->sequence($sequence);
        $validated = $request->validate([
            'prefix' => ['nullable', 'string', 'max:32'],
            'padding' => ['required', 'integer', 'min:1', 'max:18'],
            'next_value' => ['required', 'integer', 'min:'.$sequence->next_value],
            'is_active' => ['required', 'boolean'],
        ]);

        $sequence->prefix = (string) ($validated['prefix'] ?? '');
        $sequence->padding = (int) $validated['padding'];
        $sequence->next_value = (int) $validated['next_value'];
        $sequence->is_active = (bool) $validated['is_active'];
        $sequence->save();

        return redirect()->route('settings.numbering.show', $sequence->getKey())
            ->with('status', 'Numara serisi güncellendi.');
    }

    private function sequence(int $sequenceId): DocumentSequence
    {
        return DocumentSequence::query()
            ->where('company_id', $this->companyId())
            ->findOrFail($sequenceId);
    }

    private function assertIdentityAvailable(DocumentType $documentType, string $seriesCode): void
    {
        if (DocumentSequence::query()
            ->where('company_id', $this->companyId())
            ->where('document_type', $documentType->value)
            ->where('series_code', $seriesCode)
            ->exists()) {
            throw ValidationException::withMessages([
                'series_code' => 'Bu belge türü ve seri kodu şirkette zaten kullanılıyor.',
            ]);
        }
    }

    private function companyId(): int
    {
        return (int) $this->companyContext->requireCompany()->getKey();
    }
}
