<?php

namespace App\Livewire\Concerns;

/**
 * Validates the document link that originated from the shared edit center.
 * The receiving module still rechecks type, status and permissions.
 */
trait WithDocumentEditDeepLink
{
    protected function linkedDocumentId(): ?int
    {
        $value = request()->query('belge');

        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $value = (string) $value;
        if (! preg_match('/^[1-9][0-9]{0,17}$/D', $value)) {
            abort(404);
        }

        return (int) $value;
    }
}
