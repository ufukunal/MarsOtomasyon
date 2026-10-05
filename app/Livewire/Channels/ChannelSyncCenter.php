<?php

namespace App\Livewire\Channels;

use App\Livewire\Concerns\WithIdempotentMutations;
use App\Models\Period\ChannelSyncError;
use App\Models\Period\ChannelSyncEvent;
use App\Models\SalesChannelAccount;
use App\Support\Channels\ChannelSyncRecorder;
use App\Support\Period\PeriodContext;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ChannelSyncCenter extends Component
{
    use WithIdempotentMutations;

    public string $status = '';
    public string $direction = '';
    public ?int $channelAccountId = null;

    public function mount(): void
    {
        $this->seedMutationKeys(['resolveError']);
        abort_unless(auth()->user()?->can('channel_sync.view'), 403);
        PeriodContext::ensure();
    }

    public function resolveError(int $errorId, ChannelSyncRecorder $recorder): void
    {
        abort_unless(auth()->user()?->can('channel_sync.update'), 403);
        $error = ChannelSyncError::query()->findOrFail($errorId);

        $this->runPeriodMutation(
            'resolveError',
            fn () => $recorder->resolveError($error),
        );
    }

    public function render(): View
    {
        $accounts = SalesChannelAccount::query()
            ->where('company_id', PeriodContext::companyId())
            ->orderBy('platform')
            ->orderBy('name')
            ->get();
        $accountIds = $accounts->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $events = ChannelSyncEvent::query()
            ->whereIn('channel_account_id', $accountIds)
            ->when($this->channelAccountId, fn ($query) => $query->where('channel_account_id', $this->channelAccountId))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($this->direction !== '', fn ($query) => $query->where('direction', $this->direction))
            ->latest('id')
            ->limit(300)
            ->get();

        $errors = ChannelSyncError::query()
            ->with('event')
            ->whereNull('resolved_at')
            ->whereHas('event', fn ($query) => $query->whereIn('channel_account_id', $accountIds))
            ->latest('id')
            ->limit(100)
            ->get();

        return view('livewire.channels.channel-sync-center', [
            'accounts' => $accounts,
            'accountNames' => $accounts->mapWithKeys(fn ($account): array => [
                (int) $account->id => $account->platform->label().' · '.$account->name,
            ])->all(),
            'events' => $events,
            'errors' => $errors,
        ])->layout('layouts.app', ['pageTitle' => 'Kanal Sync Merkezi']);
    }
}
