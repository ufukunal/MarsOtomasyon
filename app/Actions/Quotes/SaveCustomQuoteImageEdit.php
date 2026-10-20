<?php

namespace App\Actions\Quotes;

use App\Models\Attachment;
use App\Models\QuoteCustomImageEdit;
use App\Support\Period\PeriodContext;
use App\Support\Security\SecureUploadValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class SaveCustomQuoteImageEdit
{

    /**
     * Keep the existing string instruction history readable and accept v2 editable scenes.
     * A scene always references a file owned by the same attachment.
     *
     * @return array<string,mixed>|list<string>
     */
    public function normalizeInstructions(array $input): array
    {
        if (array_is_list($input)) {
            return collect($input)->take(40)->filter(
                static fn ($step): bool => is_string($step)
            )->map(
                static fn (string $step): string => mb_substr($step, 0, 80)
            )->values()->all();
        }

        if (($input['version'] ?? null) !== 2) {
            throw ValidationException::withMessages(['customImageEditUpload' => 'Görsel düzenleme biçimi desteklenmiyor.']);
        }
        $base = $input['base_index'] ?? -1;
        $operations = $input['ops'] ?? [];
        $objects = $input['objects'] ?? [];

        if (! is_int($base) || $base < -1 || $base > 29
            || ! is_array($operations) || ! array_is_list($operations) || count($operations) > 40
            || ! is_array($objects) || ! array_is_list($objects) || count($objects) > 60) {
            throw ValidationException::withMessages(['customImageEditUpload' => 'Görsel katmanları veya işlem sayısı geçersiz.']);
        }

        $isCoordinate = static fn ($value): bool => is_numeric($value)
            && is_finite((float) $value) && (float) $value >= -6400 && (float) $value <= 6400;
        $coordinate = static fn ($value): float => round((float) $value, 2);
        $ops = [];

        foreach ($operations as $op) {
            if (! is_array($op) || ! is_string($op['kind'] ?? null)) {
                throw ValidationException::withMessages(['customImageEditUpload' => 'Geçersiz görsel işlemi.']);
            }
            $kind = $op['kind'];
            if (in_array($kind, ['rotate-left', 'rotate-right', 'flip-horizontal', 'flip-vertical'], true)) {
                $ops[] = ['kind' => $kind];
            } elseif ($kind === 'resize' && isset($op['width']) && is_numeric($op['width'])
                && (int) $op['width'] >= 1 && (int) $op['width'] <= 3200) {
                $ops[] = ['kind' => 'resize', 'width' => (int) $op['width']];
            } elseif ($kind === 'crop' && collect(['x', 'y', 'w', 'h'])->every(
                static fn (string $key): bool => isset($op[$key]) && $isCoordinate($op[$key])
            ) && (float) $op['w'] >= 3 && (float) $op['h'] >= 3
                && (float) $op['w'] <= 3200 && (float) $op['h'] <= 3200) {
                $ops[] = [
                    'kind' => 'crop', 'x' => (int) $op['x'], 'y' => (int) $op['y'],
                    'w' => (int) $op['w'], 'h' => (int) $op['h'],
                ];
            } else {
                throw ValidationException::withMessages(['customImageEditUpload' => 'Geçersiz görsel dönüşümü.']);
            }
        }

        $safeObjects = [];
        $ids = [];
        foreach ($objects as $object) {
            $type = is_array($object) ? ($object['type'] ?? null) : null;
            $id = is_array($object) ? ($object['id'] ?? null) : null;
            $color = is_array($object) ? ($object['color'] ?? null) : null;
            $width = is_array($object) ? ($object['width'] ?? 3) : null;

            if (! in_array($type, ['text', 'arrow', 'rectangle', 'circle', 'path'], true)
                || ! is_int($id) || $id < 1 || $id > 1000000 || isset($ids[$id])
                || ! is_string($color) || preg_match('/^#[0-9a-fA-F]{6}$/D', $color) !== 1
                || ! is_numeric($width) || (float) $width < 1 || (float) $width > 30) {
                throw ValidationException::withMessages(['customImageEditUpload' => 'Geçersiz çizim nesnesi.']);
            }
            $ids[$id] = true;
            $safe = ['id' => $id, 'type' => $type, 'color' => $color, 'width' => $coordinate($width)];
            if ($type === 'path') {
                $points = $object['points'] ?? [];
                if (! is_array($points) || ! array_is_list($points)
                    || count($points) < 2 || count($points) > 400) {
                    throw ValidationException::withMessages(['customImageEditUpload' => 'Çizim noktalarının sayısı geçersiz.']);
                }
                $safe['points'] = [];
                foreach ($points as $point) {
                    if (! is_array($point) || ! $isCoordinate($point['x'] ?? null)
                        || ! $isCoordinate($point['y'] ?? null)) {
                        throw ValidationException::withMessages(['customImageEditUpload' => 'Çizim koordinatı geçersiz.']);
                    }
                    $safe['points'][] = ['x' => $coordinate($point['x']), 'y' => $coordinate($point['y'])];
                }
            } else {
                if (! $isCoordinate($object['x'] ?? null) || ! $isCoordinate($object['y'] ?? null)) {
                    throw ValidationException::withMessages(['customImageEditUpload' => 'Nesne konumu geçersiz.']);
                }
                $safe['x'] = $coordinate($object['x']);
                $safe['y'] = $coordinate($object['y']);
                if ($type === 'text') {
                    $label = $object['text'] ?? null;
                    $size = $object['size'] ?? null;
                    if (! is_string($label) || mb_strlen($label) > 120
                        || ! is_numeric($size) || (float) $size < 10 || (float) $size > 180) {
                        throw ValidationException::withMessages(['customImageEditUpload' => 'Metin veya yazı boyutu geçersiz.']);
                    }
                    $safe['text'] = $label;
                    $safe['size'] = $coordinate($size);
                    $safe['bold'] = ($object['bold'] ?? false) === true;
                } else {
                    if (! $isCoordinate($object['w'] ?? null) || ! $isCoordinate($object['h'] ?? null)) {
                        throw ValidationException::withMessages(['customImageEditUpload' => 'Nesne ölçüsü geçersiz.']);
                    }
                    $safe['w'] = $coordinate($object['w']);
                    $safe['h'] = $coordinate($object['h']);
                }
            }
            $safeObjects[] = $safe;
        }

        $scene = ['version' => 2, 'base_index' => $base, 'ops' => $ops, 'objects' => $safeObjects];
        if (strlen((string) json_encode($scene)) > 85000) {
            throw ValidationException::withMessages(['customImageEditUpload' => 'Görsel işaretleme verisi çok büyük.']);
        }

        return $scene;
    }

    public function handle(Attachment $image, UploadedFile $upload, array $instructions = []): QuoteCustomImageEdit
    {
        PeriodContext::ensureWritable();
        $validated = app(SecureUploadValidator::class)->validate($upload);
        if (! in_array($validated['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)
            || $validated['size'] < 1 || $validated['size'] > 5 * 1024 * 1024) {
            throw ValidationException::withMessages(['customImageEditUpload' => 'Yalnızca 5 MB altındaki JPG, PNG ve WebP resimler kaydedilebilir.']);
        }

        $dimensions = @getimagesize($upload->getRealPath());
        if (! is_array($dimensions) || $dimensions[0] < 1 || $dimensions[1] < 1
            || $dimensions[0] > 3200 || $dimensions[1] > 3200
            || $dimensions[0] * $dimensions[1] > 10000000
            || $dimensions['mime'] !== $validated['mime']) {
            throw ValidationException::withMessages(['customImageEditUpload' => 'Görsel ölçüleri geçersiz veya çok büyük.']);
        }

        // Only bounded, typed coordinates and text reach the JSONB scene. No HTML or code.
        $safeSteps = $this->normalizeInstructions($instructions);
        if (($safeSteps['version'] ?? null) === 2
            && $safeSteps['base_index'] >= $image->editedVersions()->count()) {
            throw ValidationException::withMessages([
                'customImageEditUpload' => 'Kaynak görsel sürümü artık mevcut değil.',
            ]);
        }

        $disk = (string) config('attachments.disk', 'attachments');
        $directory = now()->format('Y/m').'/quote-image-edits';
        $path = Storage::disk($disk)->putFileAs(
            $directory, $upload, (string) Str::uuid().'.'.$validated['extension']
        );
        if ($path === false) {
            throw new RuntimeException('Düzenlenen görsel kaydedilemedi.');
        }

        try {
            return DB::connection('period')->transaction(function () use ($image, $validated, $safeSteps, $disk, $path): QuoteCustomImageEdit {
                $locked = Attachment::query()->whereKey($image->id)->lockForUpdate()->firstOrFail();
                if ($locked->editedVersions()->count() >= 30) {
                    throw ValidationException::withMessages([
                        'customImageEditUpload' => 'Görsel başına en fazla 30 düzenleme sürümü oluşturulabilir.',
                    ]);
                }
                return $locked->editedVersions()->create([
                    'disk' => $disk,
                    'path' => $path,
                    'mime' => $validated['mime'],
                    'size' => $validated['size'],
                    'instructions' => $safeSteps,
                    'created_by' => auth()->id(),
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk($disk)->delete($path);
            throw $e;
        }
    }
}
