<?php

namespace App\Modules\Core\Branch;

use App\Modules\Core\Models\Branch;
use LogicException;

final class ActiveBranchContext
{
    private ?Branch $branch = null;

    public function set(Branch $branch): void
    {
        $this->branch = $branch;
    }

    public function clear(): void
    {
        $this->branch = null;
    }

    public function id(): ?int
    {
        $id = $this->branch?->getKey();

        return is_int($id) ? $id : null;
    }

    public function branch(): ?Branch
    {
        return $this->branch;
    }

    public function requireBranch(): Branch
    {
        return $this->branch ?? throw new LogicException('Active branch context has not been initialized.');
    }
}
