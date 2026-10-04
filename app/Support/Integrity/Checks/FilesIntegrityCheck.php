<?php

namespace App\Support\Integrity\Checks;

use App\Models\Attachment;
use App\Support\Integrity\IntegrityCheck;
use App\Support\Integrity\IntegrityResult;
use Illuminate\Support\Facades\Storage;

final class FilesIntegrityCheck implements IntegrityCheck
{
    public function name(): string
    {
        return 'files';
    }

    public function run(): IntegrityResult
    {
        $started = hrtime(true);
        $checked = 0;
        $mismatches = [];

        Attachment::query()
            ->orderBy('id')
            ->chunkById(500, function ($attachments) use (&$checked, &$mismatches): void {
                foreach ($attachments as $attachment) {
                    $checked++;

                    if (Storage::disk($attachment->disk)->exists($attachment->path)) {
                        continue;
                    }

                    $mismatches[] = [
                        'attachment_id' => $attachment->id,
                        'disk' => $attachment->disk,
                        'path' => $attachment->path,
                    ];
                }
            });

        return new IntegrityResult(
            checked: $checked,
            mismatches: $mismatches,
            durationMs: (int) round((hrtime(true) - $started) / 1_000_000),
        );
    }
}
