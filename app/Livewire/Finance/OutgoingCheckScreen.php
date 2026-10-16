<?php

namespace App\Livewire\Finance;

final class OutgoingCheckScreen extends SecurityScreen
{
    protected const KIND = 'check';
    protected const MODE = 'create';
    protected const DIRECTION = 'outgoing';
    protected const TITLE = 'Verilen Çek';
}
