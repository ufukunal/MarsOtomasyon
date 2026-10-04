<?php

namespace App\Livewire\Concerns;

use App\Support\Concurrency\IdempotencyKey;
use Closure;
use Illuminate\Support\Str;

trait WithIdempotentMutations
{
    /** @var array<string, string> */
    public array $mutationKeys = [];

    /** @param list<string> $names */
    protected function seedMutationKeys(array $names): void
    {
        foreach ($names as $name) {
            $this->mutationKeys[$name] ??= (string) Str::uuid();
        }
    }

    protected function mutationKey(string $name): string
    {
        $this->seedMutationKeys([$name]);

        return $this->mutationKeys[$name];
    }

    protected function childMutationKey(string $name, int|string $child): string
    {
        return hash('sha256', $this->mutationKey($name).':'.$child);
    }

    protected function completeMutation(string $name): void
    {
        $this->mutationKeys[$name] = (string) Str::uuid();
    }

    protected function runPeriodMutation(string $name, Closure $callback): mixed
    {
        $result = IdempotencyKey::run(
            $this->mutationKey($name),
            static::class.':'.$name,
            $callback,
        );

        $this->completeMutation($name);

        return $result;
    }

    protected function runMasterMutation(string $name, Closure $callback): mixed
    {
        $result = IdempotencyKey::runMaster(
            $this->mutationKey($name),
            static::class.':'.$name,
            $callback,
        );

        $this->completeMutation($name);

        return $result;
    }
}
