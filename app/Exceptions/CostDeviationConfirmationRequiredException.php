<?php

namespace App\Exceptions;

use DomainException;

class CostDeviationConfirmationRequiredException extends DomainException
{
    /** @param list<string> $warnings */
    public function __construct(public readonly array $warnings)
    {
        parent::__construct('Maliyet sapma uyarısı kullanıcı onayı gerektiriyor.');
    }
}
