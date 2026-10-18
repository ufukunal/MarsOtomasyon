<?php

namespace App\Livewire\Concerns;

use Livewire\Attributes\Locked;

/**
 * Server-side arithmetic verification for destructive record actions.
 * Deletion methods also enforce requireVerifiedRecordDeletion() directly.
 */
trait WithVerifiedRecordDeletion
{
    #[Locked]
    public ?string $recordDeleteMethod = null;

    /** @var list<int|string> */
    #[Locked]
    public array $recordDeleteArguments = [];

    #[Locked]
    public ?int $recordDeleteFirst = null;

    #[Locked]
    public ?int $recordDeleteSecond = null;

    #[Locked]
    public ?int $recordDeleteExpiresAt = null;

    #[Locked]
    public int $recordDeleteFailures = 0;

    public string $recordDeleteAnswer = '';

    protected ?string $recordDeletionAuthorizedMethod = null;

    /** @return list<string> */
    protected function allowedRecordDeletionActions(): array
    {
        return [];
    }

    /** @param list<mixed> $arguments */
    public function requestRecordDeletion(string $method, array $arguments = []): void
    {
        abort_unless(
            in_array($method, $this->allowedRecordDeletionActions(), true)
                && method_exists($this, $method),
            403,
        );

        $expectedCount = match ($method) {
            'deleteInvoice', 'reverse' => 0,
            'deleteManualRecord' => 2,
            'deleteRecord', 'deleteFinanceDocument', 'deleteTransferRecord',
            'deletePayroll', 'deleteReturn' => 1,
            default => abort(403),
        };
        abort_unless(array_is_list($arguments) && count($arguments) === $expectedCount, 422);

        $clean = [];
        foreach ($arguments as $index => $value) {
            if (($method === 'deleteManualRecord' && $index === 0)
                || $method === 'deleteTransferRecord') {
                abort_unless(is_string($value) && $value !== '' && mb_strlen($value) <= 255, 422);
                $clean[] = $value;
            } else {
                $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                abort_unless($id !== false, 422);
                $clean[] = $id;
            }
        }
        if ($method === 'deleteManualRecord') {
            abort_unless(in_array($clean[0], ['cash', 'bank'], true), 422);
        }

        $this->resetValidation('recordDeleteAnswer');
        $this->recordDeleteMethod = $method;
        $this->recordDeleteArguments = $clean;
        $this->recordDeleteFirst = random_int(11, 39);
        $this->recordDeleteSecond = random_int(11, 39);
        $this->recordDeleteExpiresAt = time() + 180;
        $this->recordDeleteFailures = 0;
        $this->recordDeleteAnswer = '';
    }

    public function cancelRecordDeletion(): void
    {
        $this->recordDeleteMethod = null;
        $this->recordDeleteArguments = [];
        $this->recordDeleteFirst = null;
        $this->recordDeleteSecond = null;
        $this->recordDeleteExpiresAt = null;
        $this->recordDeleteFailures = 0;
        $this->recordDeleteAnswer = '';
        $this->resetValidation('recordDeleteAnswer');
    }

    public function confirmRecordDeletion(): mixed
    {
        $method = $this->recordDeleteMethod;
        abort_unless(
            $method !== null
                && in_array($method, $this->allowedRecordDeletionActions(), true)
                && $this->recordDeleteFirst !== null
                && $this->recordDeleteSecond !== null
                && $this->recordDeleteExpiresAt !== null,
            403,
        );

        if (time() > $this->recordDeleteExpiresAt) {
            $this->cancelRecordDeletion();
            $this->addError('recordDeleteAnswer', 'Doğrulama süresi doldu. Silme işlemini yeniden başlatın.');

            return null;
        }

        if (trim($this->recordDeleteAnswer) !== (string) ($this->recordDeleteFirst + $this->recordDeleteSecond)) {
            $this->recordDeleteFailures++;
            if ($this->recordDeleteFailures >= 3) {
                $this->cancelRecordDeletion();
                $this->addError('recordDeleteAnswer', 'Üç hatalı deneme. Silme işlemini yeniden başlatın.');
            } else {
                $this->recordDeleteAnswer = '';
                $this->addError('recordDeleteAnswer', 'Toplama sonucu yanlış. Kayıt silinmedi.');
            }

            return null;
        }

        $args = $this->recordDeleteArguments;
        $parameters = match ($method) {
            'deleteInvoice', 'reverse' => [],
            'deleteManualRecord' => ['type' => $args[0], 'id' => $args[1]],
            'deleteTransferRecord' => ['group' => $args[0]],
            'deleteRecord', 'deleteFinanceDocument', 'deletePayroll', 'deleteReturn' => ['id' => $args[0]],
            default => abort(403),
        };
        $this->cancelRecordDeletion();
        $this->recordDeletionAuthorizedMethod = $method;

        try {
            // Container injection resolves existing action/service parameters by type.
            return app()->call([$this, $method], $parameters);
        } finally {
            $this->recordDeletionAuthorizedMethod = null;
        }
    }

    protected function requireVerifiedRecordDeletion(string $method): void
    {
        abort_unless($this->recordDeletionAuthorizedMethod === $method, 403, 'Silme doğrulaması gerekiyor.');
    }
}
