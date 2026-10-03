<?php

namespace App\Models\Concerns;

/**
 * Geriye dönük namespace uyumluluğu.
 * Yeni kod App\Support\Concurrency\HasOptimisticLock kullanmalıdır.
 */
trait HasOptimisticLock
{
    use \App\Support\Concurrency\HasOptimisticLock;
}
