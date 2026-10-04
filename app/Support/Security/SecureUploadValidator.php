<?php

namespace App\Support\Security;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SecureUploadValidator
{
    /**
     * @return array{mime:string, extension:string, size:int}
     */
    public function validate(UploadedFile $file): array
    {
        $mime = (string) $file->getMimeType();
        $allowed = config("attachments.mime_extensions.{$mime}");
        $size = (int) ($file->getSize() ?: 0);

        if (! is_array($allowed) || $allowed === []) {
            throw ValidationException::withMessages([
                'file' => 'Dosya türüne izin verilmiyor.',
            ]);
        }

        if ($size > (int) config('attachments.max_size')) {
            throw ValidationException::withMessages([
                'file' => 'Dosya boyutu izin verilen sınırı aşıyor.',
            ]);
        }

        $originalName = $file->getClientOriginalName();

        if (substr_count($originalName, '.') > 1) {
            throw ValidationException::withMessages([
                'file' => 'Çift uzantılı dosyalara izin verilmiyor.',
            ]);
        }

        $extension = Str::lower($file->getClientOriginalExtension());

        if ($extension === 'svg' || ! in_array($extension, $allowed, true)) {
            throw ValidationException::withMessages([
                'file' => 'Dosya uzantısı içerik türüyle uyumlu değil.',
            ]);
        }

        return [
            'mime' => $mime,
            'extension' => $extension,
            'size' => $size,
        ];
    }
}
