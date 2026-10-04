<?php

namespace App\Livewire\Concerns;

use App\Support\Concurrency\IdempotencyKey;
use Closure;
use Illuminate\Support\Str;

trait HasIdempotentMutations
{
    public string $mutationKey = '';

    public function mountHasIdempotentMutations(): void
    {
        $this->rotateMutationKey();
    }

    protected function runPeriodMutation(string $action, Closure $callback): mixed
    {
        $key = $this->mutationKey;
        $result = IdempotencyKey::run($key, $action, $callback);
        $this->rotateMutationKey();

        return $result;
    }

    protected function runMasterMutation(string $action, Closure $callback): mixed
    {
        $key = $this->mutationKey;
        $result = IdempotencyKey::runMaster($key, $action, $callback);
        $this->rotateMutationKey();

        return $result;
    }

    protected function currentMutationKey(): string
    {
        return $this->mutationKey;
    }

    protected function mutationChildKey(string $scope): string
    {
        return hash('sha256', $this->mutationKey."\0".$scope);
    }

    protected function completeMutation(): void
    {
        $this->rotateMutationKey();
    }

    private function rotateMutationKey(): void
    {
        $this->mutationKey = (string) Str::uuid();
    }
}
