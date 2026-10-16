<?php

namespace App\Livewire\Finance;

/**
 * Explicit navigation screens reuse the secured security actions while
 * restricting the allowed cheque/senet kind and operation on the server.
 */
abstract class SecurityScreen extends SecuritiesCenter
{
    public function mount(): void
    {
        parent::mount();
        if ($this->scopeMode() === 'create') {
            abort_unless(auth()->user()?->can('securities.create'), 403);
        }
        if ($this->scopeMode() === 'endorse') {
            abort_unless(auth()->user()?->can('security_payrolls.create'), 403);
        }
    }

    protected const KIND = 'check';
    protected const MODE = 'list';
    protected const DIRECTION = null;
    protected const TITLE = 'Çek / Senet';

    protected function scopeKind(): ?string { return static::KIND; }
    protected function scopeDirection(): ?string { return static::DIRECTION; }
    protected function scopeMode(): string { return static::MODE; }
    protected function scopeTitle(): string { return static::TITLE; }
}
