<?php

return [
    // Legacy remains active until the complete no-period cutover is verified.
    // Setting MARS_ARCHITECTURE=doc2 before readiness is intentionally blocked.
    'mode' => env('MARS_ARCHITECTURE', 'legacy'),
];
