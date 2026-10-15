<?php

namespace App\Livewire\Concerns;

use App\Enums\DocumentType;
use App\Models\Period\Document;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait WithV65DocumentList
{
    use WithPagination;
    use WithV65FilterDrawer;

    #[Url(as: 'ara')]
    public string $search = '';

    #[Url(as: 'durum')]
    public string $status = '';

    #[Url(as: 'baslangic')]
    public string $dateFrom = '';

    #[Url(as: 'bitis')]
    public string $dateTo = '';

    public int $perPage = 25;

    public string $v65DocumentSort = '';

    public string $v65DocumentDirection = 'desc';

    /** @return array{search:string,filters:array<string,mixed>,sort:string,direction:string} */
    protected function v65ReadFilterState(): array
    {
        return [
            'search' => $this->search,
            'filters' => [
                'status' => $this->status,
                'date' => ['from' => $this->dateFrom, 'to' => $this->dateTo],
            ],
            'sort' => $this->v65DocumentSort,
            'direction' => $this->v65DocumentDirection,
        ];
    }

    /** @param array{search:string,filters:array<string,mixed>,sort:string,direction:string} $state */
    protected function v65WriteFilterState(array $state): void
    {
        $this->search = $state['search'];
        $this->status = (string) ($state['filters']['status'] ?? '');
        $this->dateFrom = (string) ($state['filters']['date']['from'] ?? '');
        $this->dateTo = (string) ($state['filters']['date']['to'] ?? '');
        $this->v65DocumentSort = $state['sort'];
        $this->v65DocumentDirection = $state['direction'];
    }

    /** @return array<string,array{label:string,type:string,options?:array<int|string,string>}> */
    protected function v65FilterDefinitions(): array
    {
        return [
            'status' => ['label' => 'Durum', 'type' => 'select', 'options' => self::documentStatusLabels()],
            'date' => ['label' => 'Belge Tarihi', 'type' => 'date-range'],
        ];
    }

    /** @return array<string,string> */
    protected function v65SortOptions(): array
    {
        return ['document_date' => 'Tarih', 'number' => 'Belge Numarası', 'grand_total' => 'Tutar', 'status' => 'Durum'];
    }

    protected function v65DefaultSort(): string
    {
        return '';
    }

    protected function v65DefaultDirection(): string
    {
        return 'desc';
    }

    /** @var array<string,bool> */
    public array $hiddenColumns = [];

    abstract protected function documentType(): DocumentType;

    abstract protected function permission(): string;

    abstract protected function pageTitle(): string;

    abstract protected function editRoute(): string;

    abstract protected function createRoute(): ?string;

    public function mount(): void
    {
        abort_unless(auth()->user()?->can($this->permission()), 403);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status', 'dateFrom', 'dateTo');
        $this->resetPage();
    }

    public function setPerPage(int $value): void
    {
        abort_unless(in_array($value, [25, 50, 100], true), 422);
        $this->perPage = $value;
        $this->resetPage();
    }

    public function toggleColumn(string $key): void
    {
        $keys = ['number', 'date', 'contact', 'status', 'total'];
        abort_unless(in_array($key, $keys, true), 422);

        $visible = array_filter($keys, fn (string $name): bool => ! ($this->hiddenColumns[$name] ?? false));
        if (count($visible) === 1 && in_array($key, $visible, true)) {
            return;
        }

        $this->hiddenColumns[$key] = ! ($this->hiddenColumns[$key] ?? false);
    }

    public function open(int|string $id): mixed
    {
        abort_unless(auth()->user()?->can($this->permission()), 403);

        return $this->redirectRoute($this->editRoute(), ['id' => $id], navigate: false);
    }

    /** @return Builder<Document> */
    protected function listQuery(): Builder
    {
        $query = Document::query()
            ->with('contact')
            ->where('document_type', $this->documentType()->value);

        $search = trim($this->search);
        if ($search !== '') {
            $needle = '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%';
            $query->where(function (Builder $builder) use ($needle): void {
                $builder->where('number', 'ilike', $needle)
                    ->orWhereHas('contact', fn (Builder $contact) => $contact
                        ->where('title', 'ilike', $needle)
                        ->orWhere('code', 'ilike', $needle));
            });
        }

        if (in_array($this->status, array_keys(self::documentStatusLabels()), true)) {
            $query->where('status', $this->status);
        }

        foreach (['dateFrom' => '>=', 'dateTo' => '<='] as $field => $operator) {
            $value = $this->{$field};
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)
                && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))) {
                $query->whereDate('document_date', $operator, $value);
            }
        }

        if ($this->v65DocumentSort !== '' && array_key_exists($this->v65DocumentSort, $this->v65SortOptions())) {
            return $query->orderBy($this->v65DocumentSort, $this->v65DocumentDirection === 'asc' ? 'asc' : 'desc')
                ->orderByDesc('id');
        }

        return $query->orderBy('document_date', $this->v65DocumentDirection === 'asc' ? 'asc' : 'desc')->orderByDesc('id');
    }

    /** @return array<string,string> */
    public static function documentStatusLabels(): array
    {
        return [
            'draft' => 'Taslak',
            'internal_review' => 'İç İnceleme',
            'customer_review' => 'Müşteri İncelemesi',
            'pending_approval' => 'Onay Bekliyor',
            'approved' => 'Onaylı',
            'sent' => 'Gönderildi',
            'confirmed' => 'Onaylandı',
            'posted' => 'Kesinleşti',
            'cancelled' => 'İptal',
            'reversed' => 'Ters Kayıt',
        ];
    }

    public function exportCsv(): StreamedResponse
    {
        abort_unless(auth()->user()?->can($this->permission()), 403);
        $name = 'belge-listesi-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function (): void {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Numara', 'Tarih', 'Cari', 'Durum', 'Tutar', 'Para Birimi'], ';');
            $this->listQuery()->chunk(500, function ($documents) use ($out): void {
                foreach ($documents as $document) {
                    // Protect spreadsheet consumers against CSV formula injection.
                    $safe = static fn (string $value): string => preg_match('/^[=+\-@\t\r]/u', $value) ? "'".$value : $value;
                    fputcsv($out, [
                        $safe((string) ($document->number ?? 'Taslak')),
                        $document->document_date?->format('d.m.Y') ?? '',
                        $safe((string) ($document->contact?->title ?? '')),
                        self::documentStatusLabels()[$document->status] ?? $document->status,
                        $document->grand_total,
                        $document->currency,
                    ], ';');
                }
            });
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function exportXlsx(): StreamedResponse
    {
        abort_unless(auth()->user()?->can($this->permission()), 403);
        $name = 'belge-listesi-'.now()->format('Ymd-His').'.xlsx';

        return response()->streamDownload(function (): void {
            $book = new Spreadsheet;
            $sheet = $book->getActiveSheet();
            $sheet->fromArray(['Numara', 'Tarih', 'Cari', 'Durum', 'Tutar', 'Para Birimi'], null, 'A1');
            $i = 2;
            $this->listQuery()->chunk(500, function ($documents) use (&$i, $sheet): void {
                foreach ($documents as $document) {
                    $sheet->setCellValueExplicit('A'.$i, (string) ($document->number ?? 'Taslak'), DataType::TYPE_STRING);
                    $sheet->setCellValue('B'.$i, $document->document_date?->format('d.m.Y'));
                    $sheet->setCellValueExplicit('C'.$i, (string) ($document->contact?->title ?? ''), DataType::TYPE_STRING);
                    $sheet->setCellValue('D'.$i, self::documentStatusLabels()[$document->status] ?? $document->status);
                    $sheet->setCellValue('E'.$i, (float) $document->grand_total);
                    $sheet->setCellValue('F'.$i, $document->currency);
                    $i++;
                }
            });
            $writer = new Xlsx($book);
            $writer->save('php://output');
            $book->disconnectWorksheets();
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function render(): View
    {
        abort_unless(auth()->user()?->can($this->permission()), 403);
        $createRoute = $this->createRoute();
        if (! auth()->user()?->can(str_replace('.view', '.create', $this->permission()))) {
            $createRoute = null;
        }

        return view('livewire.sales.document-list', [
            'documents' => $this->listQuery()->paginate($this->perPage),
            'title' => $this->pageTitle(),
            'detailRoute' => $this->editRoute(),
            'createRoute' => $createRoute,
            'statusLabels' => self::documentStatusLabels(),
        ])->layout('layouts.app', ['pageTitle' => $this->pageTitle()]);
    }
}
