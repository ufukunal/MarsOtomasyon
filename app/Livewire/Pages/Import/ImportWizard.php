<?php

namespace App\Livewire\Pages\Import;

use App\Jobs\ProcessCardImport;
use App\Models\Period\CardImportBatch;
use App\Support\Import\ImportFileReader;
use App\Support\Import\ImportMapping;
use App\Support\Import\ImportRowImporterResolver;
use App\Support\Period\PeriodContext;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;
use Livewire\WithFileUploads;

class ImportWizard extends Component
{
    use WithFileUploads;

    public int $step = 1;

    public string $type = 'contact';

    public mixed $file = null;

    public ?string $storedPath = null;

    public ?string $originalName = null;

    public ?string $fileHash = null;

    /** @var list<string> */
    public array $headers = [];

    /** @var array<string, string|null> */
    public array $mapping = [];

    /** @var list<array<string, mixed>> */
    public array $preview = [];

    /** @var array<int, array<string, mixed>> */
    public array $previewValidation = [];

    public string $errorMode = 'cancel_all';

    public ?string $batchId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('imports.create'), 403);
        PeriodContext::ensureWritable();
    }

    public function upload(ImportFileReader $reader): void
    {
        $this->validate([
            'type' => ['required', 'in:contact,product,price_list,opening_stock'],
            'file' => ['required', 'file', 'max:51200', 'mimes:xlsx,csv,json'],
        ]);

        $this->originalName = $this->file->getClientOriginalName();
        $this->storedPath = $this->file->store('card-imports', 'imports');
        $absolute = Storage::disk('imports')->path($this->storedPath);
        $this->fileHash = hash_file('sha256', $absolute);

        $rows = $reader->rows('imports', $this->storedPath, $this->originalName);
        $this->headers = array_keys($rows[0] ?? []);
        $this->preview = array_slice($rows, 0, 20);

        $this->mapping = [];

        foreach (ImportMapping::fields($this->type) as $field => $definition) {
            $match = collect($this->headers)->first(
                fn (string $header): bool => mb_strtolower($header) === mb_strtolower($field)
            );
            $this->mapping[$field] = $match;
        }

        $this->step = 2;
    }

    public function previewMapped(ImportRowImporterResolver $resolver): void
    {
        foreach (ImportMapping::fields($this->type) as $field => $definition) {
            if ($definition['required'] && empty($this->mapping[$field])) {
                $this->addError("mapping.{$field}", "{$definition['label']} için kaynak kolon seçilmelidir.");

                return;
            }
        }

        $importer = $resolver->resolve($this->type);
        $this->previewValidation = [];

        foreach ($this->preview as $index => $sourceRow) {
            $mapped = ImportMapping::map($sourceRow, $this->mapping);
            $result = $importer->validate($mapped);

            $this->previewValidation[$index] = [
                'valid' => $result->valid,
                'errors' => $result->errors,
                'mapped' => $mapped,
            ];
        }

        $this->step = 3;
    }

    public function queue(): void
    {
        PeriodContext::ensureWritable();

        $existing = CardImportBatch::query()
            ->where('file_hash', $this->fileHash)
            ->where('type', $this->type)
            ->first();

        if ($existing) {
            if ($existing->status === 'failed') {
                $actor = auth()->user();

                $existing->update([
                    'source_disk' => 'imports',
                    'source_path' => $this->storedPath,
                    'original_name' => $this->originalName,
                    'mapping' => $this->mapping,
                    'error_mode' => $this->errorMode,
                    'status' => 'pending',
                    'total_rows' => 0,
                    'success_rows' => 0,
                    'error_rows' => 0,
                    'failure_message' => null,
                    'started_at' => null,
                    'finished_at' => null,
                    'created_by' => $actor?->getAuthIdentifier(),
                    'created_by_name' => $actor?->name,
                ]);

                $existing->errors()->delete();

                ProcessCardImport::dispatch(
                    (int) PeriodContext::companyId(),
                    (int) PeriodContext::periodId(),
                    $existing->id,
                );
            }

            $this->batchId = $existing->id;
            $this->step = 4;

            return;
        }

        $actor = auth()->user();

        $batch = CardImportBatch::query()->create([
            'type' => $this->type,
            'source_disk' => 'imports',
            'source_path' => $this->storedPath,
            'original_name' => $this->originalName,
            'file_hash' => $this->fileHash,
            'mapping' => $this->mapping,
            'error_mode' => $this->errorMode,
            'status' => 'pending',
            'created_by' => $actor?->getAuthIdentifier(),
            'created_by_name' => $actor?->name,
        ]);

        ProcessCardImport::dispatch(
            (int) PeriodContext::companyId(),
            (int) PeriodContext::periodId(),
            $batch->id,
        );

        $this->batchId = $batch->id;
        $this->step = 4;
    }

    public function render(): View
    {
        return view('livewire.pages.import.import-wizard', [
            'fields' => ImportMapping::fields($this->type),
            'batch' => $this->batchId ? CardImportBatch::query()->with('errors')->find($this->batchId) : null,
        ])->layout('layouts.app', ['pageTitle' => 'İçe Aktarma']);
    }
}
