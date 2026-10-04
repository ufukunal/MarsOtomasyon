<?php

namespace App\Livewire\Sales;

use App\Actions\Documents\ReverseDocument;
use App\Actions\Sales\PostDispatch;
use App\Enums\DocumentType;

class DispatchEditor extends BaseSalesDocumentEditor
{
    public string $reversalDate = '';
    public string $reversalReason = '';

    protected function documentType(): DocumentType { return DocumentType::Dispatch; }
    protected function permissionPrefix(): string { return 'dispatches'; }
    protected function pageTitle(): string { return $this->document ? 'Satış İrsaliyesi' : 'Yeni Satış İrsaliyesi'; }

    protected function extraMutationNames(): array
    {
        return ['post', 'reverse'];
    }

    public function mount(?int $id = null): void
    {
        parent::mount($id);
        $this->reversalDate = now()->toDateString();
    }

    public function post(PostDispatch $action): void
    {
        abort_unless($this->document !== null, 422);
        $this->loadDocument($action->handle($this->document, $this->mutationKey('post'))->load('lines'));
        $this->completeMutation('post');
    }

    public function reverse(ReverseDocument $action): mixed
    {
        abort_unless($this->document !== null, 422);
        $reversal = $action->handle(
            $this->document,
            $this->reversalDate,
            $this->reversalReason,
            $this->mutationKey('reverse'),
        );
        $this->completeMutation('reverse');

        return $this->redirectRoute('sales.dispatches.edit', ['id' => $reversal->id], navigate: false);
    }
}
