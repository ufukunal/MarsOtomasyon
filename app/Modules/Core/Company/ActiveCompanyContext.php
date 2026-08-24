<?php

namespace App\Modules\Core\Company;

use App\Modules\Core\Models\Company;
use LogicException;

final class ActiveCompanyContext
{
    private ?Company $company = null;

    public function set(Company $company): void
    {
        $this->company = $company;
    }

    public function clear(): void
    {
        $this->company = null;
    }

    public function id(): ?int
    {
        $id = $this->company?->getKey();

        return is_int($id) ? $id : null;
    }

    public function company(): ?Company
    {
        return $this->company;
    }

    public function requireCompany(): Company
    {
        return $this->company ?? throw new LogicException('Active company context has not been initialized.');
    }
}
