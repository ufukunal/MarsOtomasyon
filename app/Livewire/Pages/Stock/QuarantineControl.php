<?php

namespace App\Livewire\Pages\Stock;

use App\Actions\Stock\ReleaseQuarantine;
use App\Actions\Stock\ScrapQuarantine;
use App\Models\Period\QuarantineEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithPagination;

class QuarantineControl extends Component
{
    use WithPagination;

    /** @var array<int, bool> */
    public array $selected = [];

    /** @var array<int, string> */
    public array $decisionQuantities = [];

    public string $decisionNote = '';

    public string $status = 'open';

    public function mount(): void
    {
        abort_unless(auth()->user()?->can('quarantine.view'), 403);
    }

    public function releaseSelected(ReleaseQuarantine $action): void
    {
        abort_unless(auth()->user()?->can('quarantine.update'), 403);

        foreach ($this->selectedEntryIds() as $entryId) {
            $entry = QuarantineEntry::query()->findOrFail($entryId);
            $quantity = $this->decisionQuantity($entry);

            $action->handle(
                $entry->id,
                $quantity,
                (string) Str::uuid(),
                $this->decisionNote !== '' ? $this->decisionNote : null,
            );
        }

        $this->resetDecision();
    }

    public function scrapSelected(ScrapQuarantine $action): void
    {
        abort_unless(auth()->user()?->can('quarantine.update'), 403);

        foreach ($this->selectedEntryIds() as $entryId) {
            $entry = QuarantineEntry::query()->findOrFail($entryId);
            $quantity = $this->decisionQuantity($entry);

            $action->handle(
                $entry->id,
                $quantity,
                (string) Str::uuid(),
                $this->decisionNote !== '' ? $this->decisionNote : null,
            );
        }

        $this->resetDecision();
    }

    public function render(): View
    {
        $query = QuarantineEntry::query()
            ->with(['product', 'location'])
            ->orderByRaw("CASE WHEN status IN ('pending','partial') THEN 0 ELSE 1 END")
            ->orderBy('created_at');

        if ($this->status === 'open') {
            $query->whereIn('status', ['pending', 'partial']);
        } elseif ($this->status !== 'all') {
            $query->where('status', $this->status);
        }

        $entries = $query->paginate(50);

        foreach ($entries as $entry) {
            $this->decisionQuantities[$entry->id] ??= $entry->pendingQuantity();
        }

        return view('livewire.pages.stock.quarantine-control', [
            'entries' => $entries,
        ])->layout('layouts.app', ['pageTitle' => 'Karantina']);
    }

    /** @return list<int> */
    private function selectedEntryIds(): array
    {
        return array_values(array_map(
            'intval',
            array_keys(array_filter($this->selected)),
        ));
    }

    private function decisionQuantity(QuarantineEntry $entry): string
    {
        $quantity = (string) ($this->decisionQuantities[$entry->id] ?? $entry->pendingQuantity());

        return bcadd($quantity, '0', 3);
    }

    private function resetDecision(): void
    {
        $this->selected = [];
        $this->decisionQuantities = [];
        $this->decisionNote = '';
        $this->resetPage();
    }
}
