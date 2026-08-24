<?php

namespace App\Modules\Core\Management;

use App\Modules\Core\Audit\AuditRecorder;
use App\Modules\Core\Company\ActiveCompanyContext;
use App\Modules\Core\Enums\AuditAction;
use App\Modules\Core\Enums\AuditTargetType;
use App\Modules\Core\Enums\DocumentType;
use App\Modules\Core\Models\DocumentSequence;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class DocumentSequenceController
{
    public function __construct(
        private readonly ActiveCompanyContext $companyContext,
        private readonly AuditRecorder $audit,
    ) {}

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

        $sequence = DB::transaction(function () use ($validated, $documentType, $seriesCode): DocumentSequence {
            $sequence = DocumentSequence::query()->create([
                'company_id' => $this->companyId(),
                'document_type' => $documentType,
                'series_code' => $seriesCode,
                'prefix' => (string) ($validated['prefix'] ?? ''),
                'padding' => (int) $validated['padding'],
                'next_value' => (int) $validated['next_value'],
                'is_active' => (bool) $validated['is_active'],
            ]);

            $this->audit->record(
                AuditAction::DocumentSequenceCreated,
                AuditTargetType::DocumentSequence,
                $sequence->getKey(),
                after: $this->snapshot($sequence),
            );

            return $sequence;
        });

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
        $validated = $request->validate([
            'prefix' => ['nullable', 'string', 'max:32'],
            'padding' => ['required', 'integer', 'min:1', 'max:18'],
            'next_value' => ['required', 'integer', 'min:1'],
            'is_active' => ['required', 'boolean'],
        ]);

        $sequence = DB::transaction(function () use ($sequence, $validated): DocumentSequence {
            $locked = DocumentSequence::query()
                ->where('company_id', $this->companyId())
                ->whereKey($sequence)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $validated['next_value'] < (int) $locked->next_value) {
                throw ValidationException::withMessages([
                    'next_value' => 'Sonraki sıra değeri mevcut değerin gerisine alınamaz.',
                ]);
            }

            $before = $this->snapshot($locked);
            $locked->prefix = (string) ($validated['prefix'] ?? '');
            $locked->padding = (int) $validated['padding'];
            $locked->next_value = (int) $validated['next_value'];
            $locked->is_active = (bool) $validated['is_active'];
            $locked->save();

            $this->audit->record(
                AuditAction::DocumentSequenceUpdated,
                AuditTargetType::DocumentSequence,
                $locked->getKey(),
                before: $before,
                after: $this->snapshot($locked),
            );

            return $locked;
        });

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

    /** @return array{document_type:string,series_code:string,prefix:string,padding:int,next_value:int,is_active:bool} */
    private function snapshot(DocumentSequence $sequence): array
    {
        return [
            'document_type' => $sequence->documentTypeEnum()->value,
            'series_code' => (string) $sequence->series_code,
            'prefix' => (string) $sequence->prefix,
            'padding' => (int) $sequence->padding,
            'next_value' => (int) $sequence->next_value,
            'is_active' => (bool) $sequence->is_active,
        ];
    }

    private function companyId(): int
    {
        return (int) $this->companyContext->requireCompany()->getKey();
    }
}
