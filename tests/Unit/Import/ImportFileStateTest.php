<?php

use App\Models\Period\ImportFile;

it('recognizes received and closed import files as locked', function (): void {
    foreach (['received', 'closed'] as $status) {
        expect((new ImportFile(['status' => $status]))->isLocked())->toBeTrue();
    }
    foreach (['draft', 'in_transit'] as $status) {
        expect((new ImportFile(['status' => $status]))->isLocked())->toBeFalse();
    }
});
